import { render, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, SelectControl, TextControl, PanelBody, PanelRow } from '@wordpress/components';

const widgetLibrary = [
	{ type: 'heading', label: 'Heading' },
	{ type: 'text', label: 'Text' },
	{ type: 'image', label: 'Image' },
	{ type: 'button', label: 'Button' },
	{ type: 'spacer', label: 'Spacer' },
	{ type: 'divider', label: 'Divider' },
	{ type: 'icon', label: 'Icon' },
	{ type: 'columns', label: 'Columns' },
];

const defaultNode = (type) => {
	if (type === 'columns') {
		return {
			id: `nb-${Date.now()}`,
			type: 'columns',
			children: [
				{ id: `nb-${Date.now()}-1`, type: 'column', children: [] },
				{ id: `nb-${Date.now()}-2`, type: 'column', children: [] },
			],
		};
	}

	return {
		id: `nb-${Date.now()}`,
		type,
		settings: { text: `${type} content` },
		children: [],
	};
};

const DeviceToggle = ({ device, onChange }) => (
	<div className="novabuilder-device-toggle">
		{['desktop', 'tablet', 'mobile'].map((item) => (
			<Button
				key={item}
				isPrimary={device === item}
				onClick={() => onChange(item)}
			>
				{item}
			</Button>
		))}
	</div>
);

const NodeEditor = ({ node, onUpdate }) => {
	if (!node) {
		return <p>Select a widget to edit.</p>;
	}

	const settings = node.settings || {};
	const styles = node.styles || {};
	const motion = node.motion || {};

	const updateStyle = (property, value) => {
		onUpdate({ ...node, styles: { ...styles, [property]: value } });
	};

	const updateMotion = (value) => {
		onUpdate({ ...node, motion: { ...motion, animation: value } });
	};

	return (
		<div>
			<PanelBody title="Settings" initialOpen>
				{node.type === 'heading' && (
					<PanelRow>
						<SelectControl
							label="Tag"
							value={settings.tag || 'h2'}
							options={[{ label: 'H1', value: 'h1' }, { label: 'H2', value: 'h2' }, { label: 'H3', value: 'h3' }]}
							onChange={(value) => onUpdate({ ...node, settings: { ...settings, tag: value } })}
						/>
					</PanelRow>
				)}
				{['heading', 'text', 'button'].includes(node.type) && (
					<PanelRow>
						<TextControl
							label="Text"
							value={settings.text || ''}
							onChange={(value) => onUpdate({ ...node, settings: { ...settings, text: value } })}
						/>
					</PanelRow>
				)}
				{node.type === 'image' && (
					<PanelRow>
						<TextControl
							label="Image URL"
							value={settings.url || ''}
							onChange={(value) => onUpdate({ ...node, settings: { ...settings, url: value } })}
						/>
					</PanelRow>
				)}
				{node.type === 'button' && (
					<PanelRow>
						<TextControl
							label="Link"
							value={settings.url || ''}
							onChange={(value) => onUpdate({ ...node, settings: { ...settings, url: value } })}
						/>
					</PanelRow>
				)}
			</PanelBody>
			<PanelBody title="Style" initialOpen={false}>
				<PanelRow>
					<TextControl
						label="Text Color"
						value={styles.color || ''}
						onChange={(value) => updateStyle('color', value)}
					/>
				</PanelRow>
				<PanelRow>
					<TextControl
						label="Background Color"
						value={styles['background-color'] || ''}
						onChange={(value) => updateStyle('background-color', value)}
					/>
				</PanelRow>
				<PanelRow>
					<TextControl
						label="Padding"
						value={styles.padding || ''}
						onChange={(value) => updateStyle('padding', value)}
					/>
				</PanelRow>
				<PanelRow>
					<TextControl
						label="Margin"
						value={styles.margin || ''}
						onChange={(value) => updateStyle('margin', value)}
					/>
				</PanelRow>
				<PanelRow>
					<TextControl
						label="Font Size"
						value={styles['font-size'] || ''}
						onChange={(value) => updateStyle('font-size', value)}
					/>
				</PanelRow>
			</PanelBody>
			<PanelBody title="Motion" initialOpen={false}>
				<PanelRow>
					<SelectControl
						label="Animation"
						value={motion.animation || ''}
						options={[
							{ label: 'None', value: '' },
							{ label: 'Fade Up', value: 'fade-up' },
							{ label: 'Fade In', value: 'fade-in' },
							{ label: 'Slide Left', value: 'slide-left' },
						]}
						onChange={(value) => updateMotion(value)}
					/>
				</PanelRow>
			</PanelBody>
		</div>
	);
};

const App = () => {
	const [data, setData] = useState({ version: '1.0.0', content: [], settings: {} });
	const [selectedId, setSelectedId] = useState(null);
	const [device, setDevice] = useState('desktop');

	useEffect(() => {
		if (!NovaBuilderSettings?.postId) {
			return;
		}

		apiFetch({
			path: `/novabuilder/v1/builder/${NovaBuilderSettings.postId}`,
			headers: { 'X-WP-Nonce': NovaBuilderSettings.nonce },
		}).then((response) => {
			setData(response);
		});
	}, []);

	const updateNode = (updated) => {
		const walk = (nodes) =>
			nodes.map((node) => {
				if (node.id === updated.id) {
					return updated;
				}
				if (node.children?.length) {
					return { ...node, children: walk(node.children) };
				}
				return node;
			});
		setData({ ...data, content: walk(data.content) });
	};

	const selectedNode = (() => {
		const find = (nodes) => {
			for (const node of nodes) {
				if (node.id === selectedId) {
					return node;
				}
				if (node.children?.length) {
					const child = find(node.children);
					if (child) {
						return child;
					}
				}
			}
			return null;
		};
		return find(data.content);
	})();

	const addWidget = (type) => {
		setData({
			...data,
			content: [...data.content, defaultNode(type)],
		});
	};

	const handleDragStart = (event, id) => {
		event.dataTransfer.setData('text/plain', id);
	};

	const handleDrop = (event, targetId) => {
		event.preventDefault();
		const draggedId = event.dataTransfer.getData('text/plain');
		if (!draggedId || draggedId === targetId) {
			return;
		}
		const reorder = (nodes) => {
			const currentIndex = nodes.findIndex((node) => node.id === draggedId);
			const targetIndex = nodes.findIndex((node) => node.id === targetId);
			if (currentIndex === -1 || targetIndex === -1) {
				return nodes.map((node) => ({ ...node, children: node.children ? reorder(node.children) : [] }));
			}
			const updated = [...nodes];
			const [moved] = updated.splice(currentIndex, 1);
			updated.splice(targetIndex, 0, moved);
			return updated;
		};
		setData({ ...data, content: reorder(data.content) });
	};

	const save = () => {
		apiFetch({
			path: `/novabuilder/v1/builder/${NovaBuilderSettings.postId}`,
			method: 'POST',
			headers: { 'X-WP-Nonce': NovaBuilderSettings.nonce },
			data,
		}).then(() => {
			window.alert('Saved.');
		});
	};

	return (
		<div className="novabuilder-editor">
			<div className="novabuilder-sidebar">
				<h2>Widgets</h2>
				<div className="novabuilder-widget-list">
					{widgetLibrary.map((widget) => (
						<Button key={widget.type} onClick={() => addWidget(widget.type)}>
							{widget.label}
						</Button>
					))}
				</div>
				<h2>Inspector</h2>
				<NodeEditor node={selectedNode} onUpdate={updateNode} />
			</div>
			<div className="novabuilder-canvas">
				<div className="novabuilder-toolbar">
					<DeviceToggle device={device} onChange={setDevice} />
					<Button isPrimary onClick={save}>Save</Button>
				</div>
				{data.content.length === 0 && <p>Drag widgets into the canvas.</p>}
				{data.content.map((node) => (
					<div
						key={node.id}
						className={`novabuilder-node novabuilder-node-${node.type}`}
						draggable
						onDragStart={(event) => handleDragStart(event, node.id)}
						onDragOver={(event) => event.preventDefault()}
						onDrop={(event) => handleDrop(event, node.id)}
						onClick={() => setSelectedId(node.id)}
					>
						<strong>{node.type}</strong>
						<div className="novabuilder-inline">{node.settings?.text || ''}</div>
					</div>
				))}
			</div>
		</div>
	);
};

render(<App />, document.getElementById('novabuilder-editor-root'));
