<?php namespace EugeneErg\SQLPreprocessor\Record;

use EugeneErg\SQLPreprocessor\Raw\Item as OtherItem;

/**
 * Class Item
 * @package EugeneErg\SQLPreprocessor\Record
 */
class Item extends AbstractRecord
{
    /**
     * @var OtherItem
     */
    private $item;

    /**
     * @param OtherItem $item
     * @return Container
     */
    public static function create(OtherItem $item)
    {
        $new = new self();
        $new->item = $item;

        return $new->getContainer();
    }

    /**
     * @return OtherItem
     */
    public function getItem()
    {
        return $this->item;
    }
}
