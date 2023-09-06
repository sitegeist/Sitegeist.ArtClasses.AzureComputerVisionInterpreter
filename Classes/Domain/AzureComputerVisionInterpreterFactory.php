<?php

/*
 * This file is part of the Sitegeist.ArtClasses package.
 */

declare(strict_types=1);

namespace Sitegeist\ArtClasses\AzureComputerVisionInterpreter\Domain;

use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;

/**
 * The factory for creating Azure Computer Vision Interpreters from configuration
 */
#[Flow\Scope('singleton')]
final class AzureComputerVisionInterpreterFactory
{
    public function create(
        string $endpointBaseUri,
        string $subscriptionKey,
        float $minimumScore
    ): AzureComputerVisionInterpreter {
        return new AzureComputerVisionInterpreter(
            new Uri($endpointBaseUri),
            $subscriptionKey,
            $minimumScore
        );
    }
}
