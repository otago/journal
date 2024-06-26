<?php

namespace Journal\Controllers;

use PageController;
use SilverStripe\ORM\DataObject;
use Journal\Models\Author;
use Journal\Pages\Article;
use Journal\Pages\Issue;

class JournalController extends PageController
{
    private static $allowed_actions = [
        'ViewAuthor',
        'ViewSearch',
    ];

    private static $url_handlers = [
        'search' => 'ViewSearch',
        'author/$ID' => 'ViewAuthor',
    ];

    public function ViewAuthor()
    {
        $this->JournalAuthor = $this->AuthorByID($this->getRequest()->param("ID"));
        if ($this->JournalAuthor) {
            $this->MetaTitle = $this->JournalAuthor->Title . ' - ' . $this->Title;
            return $this->renderWith(["{$this->ClassName}_author", 'Page']);
        }
        return $this->httpError(404);
    }

    public function AuthorByID($id)
    {
        $author = DataObject::get_by_id(Author::class, $id);
        if ($author && $this->BelongsToJournal($author)) {
            return $author;
        }
    }

    public function BelongsToJournal($author)
    {
        foreach ($author->Issues() as $issue) {
            if ($issue->Parent()->JournalID == $this->JournalID) {
                return true;
            }
        }
        foreach ($author->Articles() as $article) {
            if ($article->Parent()->Parent()->JournalID == $this->JournalID) {
                return true;
            }
        }
        return false;
    }

    public function ViewSearch()
    {
        $this->Query = $this->getRequest()->getVar("query");
        if ($this->Query) {
            $this->SearchResults = DataObject::create();
            $this->SearchResults->Authors = Author::get()->filterAny([
                'FirstName:PartialMatch' => $this->Query,
                'Surname:PartialMatch' => $this->Query
            ])->filterByCallback(function ($author) {
                return $this->BelongsToJournal($author);
            });
            $this->SearchResults->Volumes = $this->Children()->filterAny([
                'Title:PartialMatch' => $this->Query,
                'Content:PartialMatch' => $this->Query
            ]);
            $this->SearchResults->Issues = Issue::get()->filterAny([
                'Title:PartialMatch' => $this->Query,
                'Subtitle:PartialMatch' => $this->Query,
                'Content:PartialMatch' => $this->Query
            ])->filter([
                'Parent.ParentID' => $this->JournalID
            ]);
            $this->SearchResults->Articles = Article::get()->filterAny([
                'Title:PartialMatch' => $this->Query,
                'Content:PartialMatch' => $this->Query
            ])->filter([
                'Parent.Parent.ParentID' => $this->JournalID
            ]);
            $this->GotResults = $this->SearchResults->Authors->First() || $this->SearchResults->Volumes->First() || $this->SearchResults->Issues->First() || $this->SearchResults->Articles->First();
        } else {
            $this->Query = '" "';
        }
        return $this->renderWith(["{$this->ClassName}_results", 'Page']);
    }
}
