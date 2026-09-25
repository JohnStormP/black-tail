<?php

namespace App\Game\Domain\ValueObject;

enum ReelSymbol: string
{
    case Siamese = 'siamese';
    case Persian = 'persian';
    case Maine = 'maine';
    case Bengal = 'bengal';
    case Sphynx = 'sphynx';
    case BlackTail = 'black_tail';
}
