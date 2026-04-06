<?php

namespace App\Services\Call;

interface OfferCommandDispatcher
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function originateSip(DeliveryPlanItem $item, array $context = []): OfferCommandResult;

    /**
     * @param  array<string, mixed>  $context
     */
    public function originatePstn(DeliveryPlanItem $item, array $context = []): OfferCommandResult;
}
