import { useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
    const { layout, logo, position, color, flexColor, flexRatio } = attributes;
    const dataAttributes = layout === 'flex' ? {
        'data-pp-style-layout': 'flex',
        'data-pp-style-color': flexColor,
        'data-pp-style-ratio': flexRatio,
    } : {
        'data-pp-style-layout': 'text',
        'data-pp-style-logo-type': logo,
        'data-pp-style-logo-position': position,
        'data-pp-style-text-color': color,
    };
    const props = {
        className: 'ppcp-paylater-message-block',
        ...dataAttributes,
    };

	return <div { ...useBlockProps.save(props) }></div>;
}
