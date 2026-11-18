<?php

declare(strict_types=1);

namespace App;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TwigExtension extends AbstractExtension
{
    public function __construct(private Localizer $l10n) {}

    public function getFilters(): array
    {
        return [new TwigFilter('localdate', $this->l10n->format(...))];
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('plural', $this->l10n->plural(...))];
    }
}
