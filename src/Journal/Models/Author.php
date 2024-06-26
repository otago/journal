<?php

namespace Journal\Models;

use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Journal\Pages\Article;
use Journal\Pages\Issue;
use Journal\Pages\Journal;

class Author extends DataObject
{
    private static $table_name = 'Journal_Models_Author';

    private static $db = [
        'FirstName' => 'Varchar(255)',
        'Surname' => 'Varchar(255)',
        'ORCID' => 'Varchar(255)'
    ];

    private static $many_many = [
        'Issues' => Issue::class,
        'Articles' => Article::class
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'FirstName' => 'First name',
        'Surname' => 'Surname',
        'Issues.Count' => 'Issues',
        'Articles.Count' => 'Articles',
        'Created' => 'Created',
        'LastEdited' => 'Last edited'
    ];

    public function getLink()
    {
        return Controller::curr()->join_links(
            DataObject::get_one(Journal::class)->Link(),
            "author/$this->ID"
        );
    }

    public function getTitle()
    {
        return $this->Name();
    }

    public function Name()
    {
        return $this->FirstName . " " . $this->Surname;
    }
}
