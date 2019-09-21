<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

class Context extends ValueItem
{
    const TEMPLATE = ',|;|:|[<>=]+|[+-]+|!+|[^\\[\\]\\(\\)\\w\\s\'"+-,;:<>=]+';//test it -= +=
}