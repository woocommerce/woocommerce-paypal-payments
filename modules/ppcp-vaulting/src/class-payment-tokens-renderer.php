<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

class PaymentTokensRendered {

	public function render( array $tokens ) {
		ob_start();
		?>
		<table class="shop_table shop_table_responsive">
			<thead>
			<tr>
				<th>Payment sources</th>
				<th></th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $tokens as $token ) {
				$source = $token->source() ?? null;
				if ( $source && isset( $source->card ) ) {
					?>
						<tr>
							<td><?= $source->card->brand . ' ...' . $source->card->last_digits;?></td>
							<td>
								<a href="">Delete</a>
							</td>
						</tr>
					<?php
				}

				if ( $source && isset( $source->paypal ) ) {
					?>
						<tr>
							<td><?= $source->paypal->payer->email_address;?></td>
							<td>
								<a href="">Delete</a>
							</td>
						</tr>
					<?php
				}
				?>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}
}
