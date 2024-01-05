import { useRef, useEffect } from '@wordpress/element';

export default function PayPalMessages({
    amount,
    style,
    onRender,
}) {
    const containerRef = useRef(null);

    useEffect(() => {
        const messages = paypal.Messages({
            amount,
            style,
            onRender,
        });

        messages.render(containerRef.current)
            .catch(err => {
                // Ignore when component destroyed.
                if (!containerRef.current || containerRef.current.children.length === 0) {
                    return;
                }

                console.error(err);
            });
    }, [amount, style, onRender]);

    return <div ref={containerRef}/>
}
