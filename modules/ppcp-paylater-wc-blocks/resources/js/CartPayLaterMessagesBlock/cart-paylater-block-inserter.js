( function ( wp ) {
	const { createBlock } = wp.blocks;
	const { select, dispatch, subscribe } = wp.data;
	const getBlocks = () => select( 'core/block-editor' ).getBlocks() || [];

	const { addFilter } = wp.hooks;
	const { assign } = lodash;

	// We need to make sure the block is unlocked so that it doesn't get automatically inserted as the last block
	addFilter(
		'blocks.registerBlockType',
		'woocommerce-paypal-payments/modify-cart-paylater-messages',
		( settings, name ) => {
			if (
				name === 'woocommerce-paypal-payments/cart-paylater-messages'
			) {
				const newAttributes = assign( {}, settings.attributes, {
					lock: assign( {}, settings.attributes.lock, {
						default: assign( {}, settings.attributes.lock.default, {
							remove: false,
						} ),
					} ),
				} );

				return assign( {}, settings, {
					attributes: newAttributes,
				} );
			}
			return settings;
		}
	);

	/**
	 * Subscribes to changes in the block editor, specifically checking for the presence of 'woocommerce/cart'.
	 */
	subscribe( () => {
		const currentBlocks = getBlocks();

		currentBlocks.forEach( ( block ) => {
			if ( block.name === 'woocommerce/cart' ) {
				ensurePayLaterBlockExists( block );
			}
		} );
	} );

	/**
	 * Ensures the 'woocommerce-paypal-payments/cart-paylater-messages' block exists inside the 'woocommerce/cart' block.
	 * @param {Object} cartBlock - The cart block instance.
	 */
	function ensurePayLaterBlockExists( cartBlock ) {
		const payLaterBlock = findBlockByName(
			cartBlock.innerBlocks,
			'woocommerce-paypal-payments/cart-paylater-messages'
		);
		if ( ! payLaterBlock ) {
			waitForBlock(
				'woocommerce/cart-totals-block',
				'woocommerce-paypal-payments/cart-paylater-messages',
				'woocommerce/cart-order-summary-block'
			);
		}
	}

	/**
	 * Waits for a specific block to appear using async/await pattern before executing the insertBlockAfter function.
	 * @param {string} targetBlockName - Name of the block to wait for.
	 * @param {string} newBlockName    - Name of the new block to insert after the target.
	 * @param {string} anchorBlockName - Name of the anchor block to determine position.
	 * @param {number} attempts        - The number of attempts made to find the target block.
	 */
	async function waitForBlock(
		targetBlockName,
		newBlockName,
		anchorBlockName = '',
		attempts = 0
	) {
		const targetBlock = findBlockByName( getBlocks(), targetBlockName );
		if ( targetBlock ) {
			await delay( 1000 ); // We need this to ensure the block is fully rendered
			insertBlockAfter( targetBlockName, newBlockName, anchorBlockName );
		} else if ( attempts < 10 ) {
			// Poll up to 10 times
			await delay( 1000 ); // Wait 1 second before retrying
			await waitForBlock(
				targetBlockName,
				newBlockName,
				anchorBlockName,
				attempts + 1
			);
		} else {
			console.log(
				'Failed to find target block after several attempts.'
			);
		}
	}

	/**
	 * Delays execution by a given number of milliseconds.
	 * @param {number} ms - Milliseconds to delay.
	 * @return {Promise} A promise that resolves after the delay.
	 */
	function delay( ms ) {
		return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
	}

	/**
	 * Inserts a block after a specified block if it doesn't already exist.
	 * @param {string} targetBlockName - Name of the block to find.
	 * @param {string} newBlockName    - Name of the new block to insert.
	 * @param {string} anchorBlockName - Name of the anchor block to determine position.
	 */
	function insertBlockAfter(
		targetBlockName,
		newBlockName,
		anchorBlockName = ''
	) {
		const targetBlock = findBlockByName( getBlocks(), targetBlockName );
		if ( ! targetBlock ) {
			// Target block not found
			return;
		}

		const parentBlock = select( 'core/block-editor' ).getBlock(
			targetBlock.clientId
		);
		if (
			parentBlock.innerBlocks.some(
				( block ) => block.name === newBlockName
			)
		) {
			// The block is already inserted next to the target block
			return;
		}

		let offset = 0;
		if ( anchorBlockName !== '' ) {
			// Find the anchor block and calculate the offset
			const anchorIndex = parentBlock.innerBlocks.findIndex(
				( block ) => block.name === anchorBlockName
			);
			offset = parentBlock.innerBlocks.length - ( anchorIndex + 1 );
		}

		const newBlock = createBlock( newBlockName );

		// Insert the block at the correct position
		dispatch( 'core/block-editor' ).insertBlock(
			newBlock,
			parentBlock.innerBlocks.length - offset,
			parentBlock.clientId
		);

		// Lock the block after it has been inserted
		dispatch( 'core/block-editor' ).updateBlockAttributes(
			newBlock.clientId,
			{
				lock: { remove: true },
			}
		);
	}

	/**
	 * Recursively searches for a block by name among all blocks.
	 * @param {Array}  blocks    - The array of blocks to search.
	 * @param {string} blockName - The name of the block to find.
	 * @return {Object|null} The found block, or null if not found.
	 */
	function findBlockByName( blocks, blockName ) {
		for ( const block of blocks ) {
			if ( block.name === blockName ) {
				return block;
			}
			if ( block.innerBlocks.length > 0 ) {
				const foundBlock = findBlockByName(
					block.innerBlocks,
					blockName
				);
				if ( foundBlock ) {
					return foundBlock;
				}
			}
		}
		return null;
	}
} )( window.wp );
