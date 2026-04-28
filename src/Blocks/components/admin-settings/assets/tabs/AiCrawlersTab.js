/**
 * AI Crawlers tab — per-bot allow/disallow policy and site-wide defaults.
 */

import { __ } from '@wordpress/i18n';
import { ToggleControl, SelectControl, ExternalLink, Notice } from '@wordpress/components';

/**
 * Bots grouped by vendor. Mirrors AiBotRegistry::getBots() on the PHP side.
 * Keep in sync with src/Config/AiBotRegistry.php.
 *
 * lastVerified: '2026-04-27'
 */
const AI_BOT_REGISTRY = [
	{
		vendor: 'OpenAI',
		bots: [
			{ id: 'gptbot',       name: 'GPTBot',       category: 'training', help: __('Training data collection for OpenAI models.', 'eightshift-seo') },
			{ id: 'oai-searchbot',name: 'OAI-SearchBot',category: 'search',   help: __('Powers ChatGPT web search results.', 'eightshift-seo') },
			{ id: 'chatgpt-user', name: 'ChatGPT-User', category: 'user',     help: __('Real-time retrieval during live ChatGPT sessions.', 'eightshift-seo') },
		],
	},
	{
		vendor: 'Anthropic',
		bots: [
			{ id: 'claudebot',        name: 'ClaudeBot',        category: 'training', help: __('Training data collection for Claude models.', 'eightshift-seo') },
			{ id: 'claude-searchbot', name: 'Claude-SearchBot', category: 'search',   help: __('Powers Claude web search results.', 'eightshift-seo') },
			{ id: 'claude-user',      name: 'Claude-User',      category: 'user',     help: __('Real-time retrieval during live Claude sessions.', 'eightshift-seo') },
		],
	},
	{
		vendor: 'Google',
		bots: [
			{ id: 'google-extended', name: 'Google-Extended', category: 'training', help: __('Used for Gemini and Google AI Overviews training. Does NOT affect classic Google Search.', 'eightshift-seo') },
		],
	},
	{
		vendor: 'Perplexity',
		bots: [
			{ id: 'perplexitybot',   name: 'PerplexityBot',   category: 'search', help: __('Indexes content for Perplexity AI search.', 'eightshift-seo') },
			{ id: 'perplexity-user', name: 'Perplexity-User', category: 'user',   help: __('Real-time retrieval during live Perplexity sessions.', 'eightshift-seo') },
		],
	},
	{
		vendor: 'Apple',
		bots: [
			{ id: 'applebot-extended', name: 'Applebot-Extended', category: 'training', help: __('Used for Apple Intelligence and Siri training.', 'eightshift-seo') },
		],
	},
	{
		vendor: 'Meta',
		bots: [
			{ id: 'meta-externalagent', name: 'Meta-ExternalAgent', category: 'training', help: __('Used for Meta AI / Llama model training.', 'eightshift-seo') },
		],
	},
	{
		vendor: 'xAI',
		bots: [
			{ id: 'xai-bot', name: 'xAI-Bot', category: 'training', help: __('Used for Grok model training.', 'eightshift-seo') },
		],
	},
	{
		vendor: 'Other AI',
		bots: [
			{ id: 'ccbot',           name: 'CCBot',           category: 'training', help: __('Common Crawl — large open training dataset used by many model providers.', 'eightshift-seo') },
			{ id: 'bytespider',      name: 'Bytespider',      category: 'training', help: __('ByteDance (TikTok) training crawler.', 'eightshift-seo') },
			{ id: 'mistralai-user',  name: 'MistralAI-User',  category: 'user',     help: __('Real-time retrieval during live Mistral sessions.', 'eightshift-seo') },
			{ id: 'cohere-ai',       name: 'cohere-ai',       category: 'training', help: __('Cohere model training crawler.', 'eightshift-seo') },
			{ id: 'duckassistbot',   name: 'DuckAssistBot',   category: 'search',   help: __('Powers DuckDuckGo AI answers.', 'eightshift-seo') },
			{ id: 'youbot',          name: 'YouBot',          category: 'search',   help: __('Powers You.com AI search.', 'eightshift-seo') },
			{ id: 'ai2bot',          name: 'Ai2Bot',          category: 'training', help: __('Allen Institute for AI training data collection.', 'eightshift-seo') },
		],
	},
];

const CATEGORY_LABELS = {
	training: __('Training', 'eightshift-seo'),
	search:   __('Search',   'eightshift-seo'),
	user:     __('User',     'eightshift-seo'),
};

const POLICY_OPTIONS = [
	{ label: __('Allow (default)', 'eightshift-seo'), value: 'allow' },
	{ label: __('Disallow',        'eightshift-seo'), value: 'disallow' },
];

const LAST_VERIFIED = '2026-04-27';

export const AiCrawlersTab = ({ settings, onChange }) => {
	const ai = settings.aiCrawlers ?? { enabled: true, defaultPolicy: 'allow', perBot: {} };

	const setAi = (key, value) =>
		onChange({ ...settings, aiCrawlers: { ...ai, [key]: value } });

	const setBotPolicy = (botId, policy) => {
		const perBot = { ...(ai.perBot ?? {}) };
		if (policy === ai.defaultPolicy) {
			// Removing an explicit override that matches the default keeps the file minimal.
			delete perBot[botId];
		} else {
			perBot[botId] = { ...(perBot[botId] ?? {}), policy };
		}
		setAi('perBot', perBot);
	};

	const getBotPolicy = (botId) => {
		return ai.perBot?.[botId]?.policy ?? ai.defaultPolicy ?? 'allow';
	};

	const disallowCount = Object.values(ai.perBot ?? {}).filter((b) => b?.policy === 'disallow').length;

	return (
		<div className="es-seo-tab">
			<h2>{__('AI Crawlers', 'eightshift-seo')}</h2>
			<p className="description">
				{__('Control which AI bots may crawl your site. Stanzas are written to robots.txt — only bots whose policy differs from the default produce an entry.', 'eightshift-seo')}
			</p>
			<p className="description">
				{/* translators: %s: date string */}
				{__('Bot registry last verified:', 'eightshift-seo')} <strong>{LAST_VERIFIED}</strong>
				{' · '}
				<ExternalLink href="https://docs.eightshift.com/seo/ai-crawlers">
					{__('Documentation', 'eightshift-seo')}
				</ExternalLink>
			</p>

			<ToggleControl
				label={__('Enable AI crawler governance', 'eightshift-seo')}
				help={__('When off, no AI-crawler stanzas are written to robots.txt regardless of the settings below.', 'eightshift-seo')}
				checked={ai.enabled !== false}
				onChange={(val) => setAi('enabled', val)}
				__nextHasNoMarginBottom
			/>

			{ai.enabled !== false && (
				<>
					<SelectControl
						label={__('Default policy for unlisted bots', 'eightshift-seo')}
						help={__('Applies to any bot not individually configured below. "Allow" means no robots.txt stanza is emitted (fully open).', 'eightshift-seo')}
						value={ai.defaultPolicy ?? 'allow'}
						options={POLICY_OPTIONS}
						onChange={(val) => setAi('defaultPolicy', val)}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>

					{disallowCount > 0 && (
						<Notice status="info" isDismissible={false}>
							{/* translators: %d: number of disallowed bots */}
							{__(`${disallowCount} bot(s) individually set to Disallow. Only these differ from the default policy and produce robots.txt stanzas.`, 'eightshift-seo')}
						</Notice>
					)}

					<hr />

					{AI_BOT_REGISTRY.map((group) => (
						<div key={group.vendor} style={{ marginBottom: '24px' }}>
							<h3 style={{ margin: '0 0 8px' }}>{group.vendor}</h3>

							{group.bots.map((bot) => {
								const currentPolicy = getBotPolicy(bot.id);
								const isOverridden  = !!ai.perBot?.[bot.id]?.policy;

								return (
									<div
										key={bot.id}
										style={{
											display: 'flex',
											alignItems: 'flex-start',
											gap: '12px',
											marginBottom: '12px',
											paddingBottom: '12px',
											borderBottom: '1px solid #e0e0e0',
										}}
									>
										<div style={{ flex: 1 }}>
											<strong>{bot.name}</strong>
											{' '}
											<span
												style={{
													fontSize: '11px',
													padding: '1px 6px',
													borderRadius: '10px',
													background: bot.category === 'training' ? '#fff3cd' : bot.category === 'search' ? '#d1ecf1' : '#d4edda',
													color: bot.category === 'training' ? '#856404' : bot.category === 'search' ? '#0c5460' : '#155724',
												}}
											>
												{CATEGORY_LABELS[bot.category]}
											</span>
											<p className="description" style={{ margin: '4px 0 0' }}>{bot.help}</p>
										</div>
										<div style={{ flexShrink: 0, minWidth: '160px' }}>
											<SelectControl
												label=""
												hideLabelFromVision
												value={currentPolicy}
												options={POLICY_OPTIONS}
												onChange={(val) => setBotPolicy(bot.id, val)}
												style={isOverridden ? { fontWeight: 'bold' } : {}}
												__nextHasNoMarginBottom
												__next40pxDefaultSize
											/>
										</div>
									</div>
								);
							})}
						</div>
					))}
				</>
			)}
		</div>
	);
};
