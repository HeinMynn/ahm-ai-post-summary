import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	BlockControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	ToolbarGroup,
	ToolbarButton,
	Button,
	Spinner,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';

const cfg = () => window.ahmaipsuBlock || {};

function isEnabledValue(value) {
	return value === true || value === 1 || value === '1';
}

function themeFromClassName(className, fallback) {
	const cls = className || '';
	if (cls.indexOf('is-style-minimal') !== -1) {
		return 'minimal';
	}
	if (cls.indexOf('is-style-card') !== -1) {
		return 'card';
	}
	return fallback || 'classic';
}

function titleFor(theme, contentType, customSummary, customTakeaways) {
	if (contentType === 'key_takeaways') {
		if (customTakeaways) {
			return customTakeaways;
		}
		const takeaways = {
			classic: '🔑 Key Takeaways',
			minimal: 'Key Takeaways',
			modern: '🎯 Key Takeaways',
			elegant: 'Key Takeaways',
			card: '📋 Key Takeaways',
		};
		return takeaways[theme] || takeaways.classic;
	}
	if (customSummary) {
		return customSummary;
	}
	const titles = {
		classic: '📝 Summary',
		minimal: 'Summary',
		modern: '🤖 Summary',
		elegant: 'Summary',
		card: '📄 Summary',
	};
	return titles[theme] || titles.classic;
}

function formatBody(summary, contentType) {
	if (contentType !== 'key_takeaways') {
		return summary;
	}
	const lines = String(summary)
		.split('\n')
		.map((line) => line.trim())
		.filter(Boolean);
	return (
		<ul>
			{lines.map((line, i) => (
				<li key={i}>{line.replace(/^[-•*]\s*/, '')}</li>
			))}
		</ul>
	);
}

export default function Edit({ attributes, setAttributes, className }) {
	const { showTitle, showDisclaimer } = attributes;
	const [busy, setBusy] = useState(false);
	const { editPost } = useDispatch('core/editor');
	const { createErrorNotice, createSuccessNotice } = useDispatch('core/notices');

	const { summary, enabled, contentType } = useSelect((select) => {
		const meta = select('core/editor').getEditedPostAttribute('meta') || {};
		return {
			summary: meta._ahmaipsu_content || '',
			enabled: meta._ahmaipsu_enabled,
			contentType: meta._ahmaipsu_content_type || 'summary',
		};
	}, []);

	const settings = cfg();
	const postEnabled = isEnabledValue(enabled);
	const theme = themeFromClassName(className, settings.theme);
	const heading = titleFor(
		theme,
		contentType,
		settings.customSummaryTitle,
		settings.customTakeawaysTitle
	);
	const hasSummary = !!(summary && String(summary).trim());
	const blockProps = useBlockProps({
		className: 'ahmaipsu-summary-box ahmaipsu-theme-' + theme + (!hasSummary || !postEnabled ? ' ahmaipsu-block-placeholder' : ''),
	});

	function ajax(action, extra) {
		const body = new window.FormData();
		body.append('action', action);
		body.append('post_id', String(settings.postId || 0));
		Object.keys(extra || {}).forEach((key) => body.append(key, extra[key]));
		return window.fetch(settings.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		}).then((res) => res.json());
	}

	function onGenerate() {
		setBusy(true);
		ajax('ahmaipsu_regenerate_instantly', { nonce: settings.regenerateNonce })
			.then((json) => {
				setBusy(false);
				if (!json || !json.success) {
					const msg = (json && json.data) ? json.data : __('Failed to generate summary.', 'ahm-ai-post-summary');
					createErrorNotice(String(msg), { type: 'snackbar' });
					return;
				}
				editPost({
					meta: { _ahmaipsu_content: json.data.summary },
				});
				createSuccessNotice(json.data.message || __('Summary generated.', 'ahm-ai-post-summary'), { type: 'snackbar' });
			})
			.catch(() => {
				setBusy(false);
				createErrorNotice(__('Failed to generate summary.', 'ahm-ai-post-summary'), { type: 'snackbar' });
			});
	}

	function onTurnOn() {
		setBusy(true);
		ajax('ahmaipsu_enable_post', { nonce: settings.enableNonce })
			.then((json) => {
				setBusy(false);
				if (!json || !json.success) {
					const msg = (json && json.data) ? json.data : __('Could not turn summaries on.', 'ahm-ai-post-summary');
					createErrorNotice(String(msg), { type: 'snackbar' });
					return;
				}
				editPost({ meta: { _ahmaipsu_enabled: '1' } });
			})
			.catch(() => {
				setBusy(false);
				createErrorNotice(__('Could not turn summaries on.', 'ahm-ai-post-summary'), { type: 'snackbar' });
			});
	}

	let body;
	if (!postEnabled) {
		body = (
			<>
				{showTitle && <h4 className="ahmaipsu-summary-title">{__('Summary', 'ahm-ai-post-summary')}</h4>}
				<div className="ahmaipsu-summary-content">{__('Summary is off for this post.', 'ahm-ai-post-summary')}</div>
				<p className="ahmaipsu-block-helper">{__('Turn it on to show a TL;DR here.', 'ahm-ai-post-summary')}</p>
				<Button variant="secondary" onClick={onTurnOn} disabled={busy}>
					{busy ? <Spinner /> : __('Turn on', 'ahm-ai-post-summary')}
				</Button>
			</>
		);
	} else if (!hasSummary) {
		body = (
			<>
				{showTitle && <h4 className="ahmaipsu-summary-title">{heading}</h4>}
				<div className="ahmaipsu-summary-content">{__('No summary yet.', 'ahm-ai-post-summary')}</div>
				<p className="ahmaipsu-block-helper">{__('Generate one, or it will fill when this post is published.', 'ahm-ai-post-summary')}</p>
				<Button variant="secondary" onClick={onGenerate} disabled={busy}>
					{busy ? __('Generating summary…', 'ahm-ai-post-summary') : __('Generate summary', 'ahm-ai-post-summary')}
				</Button>
			</>
		);
	} else {
		body = (
			<>
				{showTitle && <h4 className="ahmaipsu-summary-title">{heading}</h4>}
				<div className="ahmaipsu-summary-content">{formatBody(summary, contentType)}</div>
				{showDisclaimer && (
					<div className="ahmaipsu-summary-disclaimer">
						<small>ℹ️ {settings.disclaimer}</small>
					</div>
				)}
			</>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('AI Summary', 'ahm-ai-post-summary')}>
					<ToggleControl
						label={__('Show title', 'ahm-ai-post-summary')}
						checked={!!showTitle}
						onChange={(value) => setAttributes({ showTitle: value })}
					/>
					<ToggleControl
						label={__('Show disclaimer', 'ahm-ai-post-summary')}
						checked={!!showDisclaimer}
						onChange={(value) => setAttributes({ showDisclaimer: value })}
					/>
				</PanelBody>
			</InspectorControls>
			{postEnabled && (
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							icon="update"
							label={hasSummary ? __('Regenerate', 'ahm-ai-post-summary') : __('Generate summary', 'ahm-ai-post-summary')}
							onClick={onGenerate}
							disabled={busy}
						/>
					</ToolbarGroup>
				</BlockControls>
			)}
			<div {...blockProps}>{busy && hasSummary && postEnabled ? <p>{__('Generating summary…', 'ahm-ai-post-summary')}</p> : body}</div>
		</>
	);
}
