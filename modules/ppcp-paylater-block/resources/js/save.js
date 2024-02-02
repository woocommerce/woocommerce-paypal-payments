import { useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
    const { layout, logo, position, color, flexColor, flexRatio, placement, id } = attributes;
    const paypalAttributes = layout === 'flex' ? {
        'data-pp-style-layout': 'flex',
        'data-pp-style-color': flexColor,
        'data-pp-style-ratio': flexRatio,
    } : {
        'data-pp-style-layout': 'text',
        'data-pp-style-logo-type': logo,
        'data-pp-style-logo-position': position,
        'data-pp-style-text-color': color,
    };
    if (placement && placement !== 'auto') {
        paypalAttributes['data-pp-placement'] = placement;
    }
    const props = {
        className: 'ppcp-paylater-message-block',
        id,
        ...paypalAttributes,
    };

	return <div { ...useBlockProps.save(props) }></div>;
}
