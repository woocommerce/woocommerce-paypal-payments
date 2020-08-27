<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Assets;

interface SmartButtonInterface {

	public function renderWrapper(): bool;

	public function enqueue(): bool;

	public function canSaveVaultToken(): bool;
}
