<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

class InvalidRangeMessage
{
    #[Assert\Range(min: 1, max: 10, minMessage: 'too small')]
    public int $count = 1;
}

class InvalidRangeBothMessages
{
    #[Assert\Range(min: 0, max: 5, maxMessage: 'too high', minMessage: 'too low')]
    public int $score = 3;
}

class ValidRangeWithNotInRangeMessage
{
    #[Assert\Range(min: 1, max: 10, notInRangeMessage: 'between 1 and 10')]
    public int $rating = 5;
}

class ValidRangeWithSingleBoundMessage
{
    #[Assert\Range(min: 0, minMessage: 'should be positive')]
    public int $offset = 0;
}
