<?php

namespace OP\Journals\Traits;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\View\Parsers\URLSegmentFilter;

trait JournalTrait
{
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $defaultSegment = SiteTree::create()->generateURLSegment(_t(
            'SilverStripe\\CMS\\Controllers\\CMSMain.NEWPAGE',
            'New {pagetype}',
            ['pagetype' => $this->i18n_singular_name()]
        ));
        if ((!$this->URLSegment || $this->URLSegment == $defaultSegment) && $this->Title) {
            $this->URLSegment = SiteTree::create()->generateURLSegment($this->Title);
        } elseif ($this->isChanged('URLSegment', 2)) {
            $filter = URLSegmentFilter::create();
            $this->URLSegment = $filter->filter($this->URLSegment);
            if (!$this->URLSegment) {
                $this->URLSegment = "Issue-$this->ID";
            }
        }
    }
}
