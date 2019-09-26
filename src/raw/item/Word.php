<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

use EugeneErg\SQLPreprocessor\Raw\Item;

class Word extends Item
{
    const TEMPLATE = '\\b\\w+\\b';
}