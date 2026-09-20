<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase2;

interface Phase2Provider
{
    public function translate(Phase2ProviderRequest $request): Phase2ProviderResponse|Phase2ProviderTechnicalFailure;
}