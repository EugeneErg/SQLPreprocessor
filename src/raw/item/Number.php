<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

class Number extends ValueItem
{
    const TEMPLATE = '\\b(?:\\d*\\.\\d+|\\d+\\.?\\d*)(?:[Ee][+-]?\\d+)?\\b';
}