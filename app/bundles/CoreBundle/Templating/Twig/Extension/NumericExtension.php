<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Templating\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigTest;

class NumericExtension extends AbstractExtension
{
    public function getTests()
    {
        return [
            new TwigTest('numeric', fn ($value) => is_numeric($value)),
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('int', function ($value) {
                return (int) $value;
            }),
            new TwigFilter('array', function (object $value) {
                return (array) $value;
            }),
        ];
    }
}
