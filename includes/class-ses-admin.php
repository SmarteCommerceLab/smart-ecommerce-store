<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Admin {
	const MENU_SLUG = 'smart-ecommerce-store';
	const MENU_POSITION = 82;
	private static $captured_notices = '';
	private static $notice_buffer_level = null;

	public static function register() {
		add_action('admin_menu', array(__CLASS__, 'menu'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
		add_action('admin_notices', array(__CLASS__, 'capture_notices_start'), -9999);
		add_action('admin_notices', array(__CLASS__, 'capture_notices_end'), 999999);
		add_action('all_admin_notices', array(__CLASS__, 'capture_notices_start'), -9999);
		add_action('all_admin_notices', array(__CLASS__, 'capture_notices_end'), 999999);
	}

	public static function get_subpages() {
		return array(
			array(
				'slug' => 'ses-catalog',
				'page_title' => __('Catalogo prodotti', 'smart-ecommerce-store'),
				'menu_title' => __('Catalogo', 'smart-ecommerce-store'),
				'capability' => 'install_plugins',
				'callback' => array(__CLASS__, 'render_catalog'),
				'icon' => 'dashicons-screenoptions',
				'description' => __('Confronta le edizioni e scegli il prodotto adatto.', 'smart-ecommerce-store'),
			),
			array(
				'slug' => 'ses-my-products',
				'page_title' => __('I miei prodotti', 'smart-ecommerce-store'),
				'menu_title' => __('I miei prodotti', 'smart-ecommerce-store'),
				'capability' => 'install_plugins',
				'callback' => array(__CLASS__, 'render_my_products'),
				'icon' => 'dashicons-yes-alt',
				'description' => __('Installa, attiva e controlla i prodotti già acquistati o disponibili.', 'smart-ecommerce-store'),
			),
			array(
				'slug' => 'ses-support', 'page_title' => __('Assistenza', 'smart-ecommerce-store'),
				'menu_title' => __('Assistenza', 'smart-ecommerce-store'), 'capability' => 'install_plugins',
				'callback' => array(__CLASS__, 'render_support'), 'icon' => 'dashicons-sos',
				'description' => __('Guide, diagnostica e report tecnico sicuro.', 'smart-ecommerce-store'),
			),
		);
	}

	public static function menu() {
		add_menu_page(
			__('Smart eCommerce Store', 'smart-ecommerce-store'),
			__('Smart Store', 'smart-ecommerce-store'),
			'install_plugins', self::MENU_SLUG,
			array(__CLASS__, 'render_dashboard'), 'dashicons-store', self::MENU_POSITION
		);
		foreach (self::get_subpages() as $page) {
			add_submenu_page(self::MENU_SLUG, $page['page_title'], $page['menu_title'], $page['capability'], $page['slug'], $page['callback']);
		}
		global $submenu;
		if (isset($submenu[self::MENU_SLUG][0][0])) { $submenu[self::MENU_SLUG][0][0] = __('Store', 'smart-ecommerce-store'); }
	}

	public static function assets($hook) {
		if (false === strpos((string) $hook, 'smart-ecommerce-store') && false === strpos((string) $hook, 'ses-')) { return; }
		wp_enqueue_style('dashicons');
		wp_enqueue_style('ses-admin', SES_URL . 'assets/admin.css', array('dashicons'), SES_VERSION);
		wp_enqueue_style('ses-catalog-sections', SES_URL . 'assets/catalog-sections.css', array('ses-admin'), SES_VERSION);
		wp_enqueue_style('ses-actions', SES_URL . 'assets/actions.css', array('ses-admin'), SES_VERSION);
		wp_enqueue_style('ses-licenses', SES_URL . 'assets/licenses.css', array('ses-admin'), SES_VERSION);
	}

	public static function render_dashboard() {
		self::template(__('Scegli, acquista e installa', 'smart-ecommerce-store'), __('Un percorso unico per ottenere i prodotti Smart eCommerce sul tuo sito.', 'smart-ecommerce-store'), array(__CLASS__, 'dashboard_content'));
	}

	public static function render_catalog() {
		self::template(__('Catalogo prodotti', 'smart-ecommerce-store'), __('Confronta Free e Premium prima di scegliere.', 'smart-ecommerce-store'), array(__CLASS__, 'catalog_content'));
	}

	public static function render_my_products() {
		self::template(__('I miei prodotti', 'smart-ecommerce-store'), __('Azioni disponibili per i prodotti installati o già acquistati.', 'smart-ecommerce-store'), array(__CLASS__, 'my_products_content'));
	}

	public static function render_support() {
		self::template(__('Assistenza', 'smart-ecommerce-store'), __('Percorso self-service per catalogo, installazione e licenze.', 'smart-ecommerce-store'), array(__CLASS__, 'support_content'));
	}

	public static function support_content() {
		$report = wp_json_encode(array('product' => 'Smart eCommerce Store', 'version' => SES_PRODUCT_VERSION, 'wordpress' => get_bloginfo('version'), 'php' => PHP_VERSION, 'locale' => get_locale(), 'multisite' => is_multisite()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		?><section class="ses-support"><h2><?php esc_html_e('Sequenza raccomandata', 'smart-ecommerce-store'); ?></h2><ol><li><?php esc_html_e('Aggiorna il catalogo.', 'smart-ecommerce-store'); ?></li><li><?php esc_html_e('Controlla disponibilità, installazione e licenza.', 'smart-ecommerce-store'); ?></li><li><?php esc_html_e('Copia il report tecnico.', 'smart-ecommerce-store'); ?></li><li><?php esc_html_e('Consulta la guida ufficiale.', 'smart-ecommerce-store'); ?></li></ol><p><a class="button button-primary" href="https://kb.smartecommerce.it/prodotti/smart-ecommerce-store/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Apri la guida', 'smart-ecommerce-store'); ?></a></p><h2><?php esc_html_e('Report tecnico privo di segreti', 'smart-ecommerce-store'); ?></h2><textarea class="large-text code" rows="8" readonly><?php echo esc_textarea($report); ?></textarea><p class="description"><?php esc_html_e('Non include URL, utenti, email, licenze, token o contenuti.', 'smart-ecommerce-store'); ?></p></section><?php
	}

	private static function template($title, $description, $callback) {
		if (!current_user_can('install_plugins')) { wp_die(esc_html__('Permessi insufficienti.', 'smart-ecommerce-store')); }
		$current = sanitize_key(wp_unslash($_GET['page'] ?? self::MENU_SLUG));
		$scheme = get_user_option('admin_color');
		global $_wp_admin_css_colors;
		$header = isset($_wp_admin_css_colors[$scheme]->colors[1]) ? $_wp_admin_css_colors[$scheme]->colors[1] : '#2c3338';
		$pages = array_merge(array(array(
			'slug' => self::MENU_SLUG, 'menu_title' => __('Store', 'smart-ecommerce-store'),
			'icon' => 'dashicons-store', 'description' => __('Percorso di acquisto guidato.', 'smart-ecommerce-store'),
		)), self::get_subpages());
		?>
		<div class="wrap smart-admin-wrap" style="--smart-header:<?php echo esc_attr(sanitize_hex_color($header) ?: '#2c3338'); ?>">
			<header class="smart-admin-header">
				<div class="smart-admin-header-brand"><span class="smart-admin-logo">Se</span><div><strong><?php echo esc_html(SES_PRODUCT_NAME); ?></strong><small><?php esc_html_e('Catalogo ufficiale Smart eCommerce', 'smart-ecommerce-store'); ?></small></div></div>
				<div class="smart-admin-header-actions"><span>v<?php echo esc_html(SES_PRODUCT_VERSION); ?></span></div>
			</header>
			<div class="smart-admin-shell">
				<aside class="smart-admin-sidebar"><nav class="smart-admin-nav" aria-label="<?php esc_attr_e('Navigazione Store', 'smart-ecommerce-store'); ?>">
					<?php foreach ($pages as $index => $page) : $active = $current === $page['slug']; ?>
						<?php if (0 === $index) : ?><span class="smart-admin-nav-section"><?php esc_html_e('Acquista', 'smart-ecommerce-store'); ?></span><?php elseif (2 === $index) : ?><span class="smart-admin-nav-section"><?php esc_html_e('I tuoi prodotti', 'smart-ecommerce-store'); ?></span><?php endif; ?>
						<a href="<?php echo esc_url(add_query_arg('page', $page['slug'], admin_url('admin.php'))); ?>" class="smart-admin-nav-item<?php echo $active ? ' smart-admin-nav-item-active' : ''; ?>" <?php echo $active ? 'aria-current="page"' : ''; ?>><span class="smart-admin-nav-icon"><span class="dashicons <?php echo esc_attr($page['icon']); ?>"></span></span><span class="smart-admin-nav-copy"><strong><?php echo esc_html($page['menu_title']); ?></strong><small><?php echo esc_html($page['description']); ?></small></span></a>
					<?php endforeach; ?>
				</nav></aside>
				<main class="smart-admin-main">
					<div class="smart-admin-pathbar"><a href="<?php echo esc_url(add_query_arg('page', self::MENU_SLUG, admin_url('admin.php'))); ?>"><?php echo esc_html(SES_PRODUCT_NAME); ?></a><span aria-hidden="true">›</span><span><?php echo esc_html($title); ?></span></div>
					<div class="smart-admin-body">
						<header class="smart-admin-page-header"><div><h1><?php echo esc_html($title); ?></h1><p><?php echo esc_html($description); ?></p></div><a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => $current, 'refresh' => 1), admin_url('admin.php')), 'ses_refresh')); ?>"><?php esc_html_e('Aggiorna catalogo', 'smart-ecommerce-store'); ?></a></header>
						<div class="smart-admin-notices"><?php self::notice(); echo wp_kses_post(self::$captured_notices); ?></div>
						<div class="smart-admin-content"><?php call_user_func($callback); ?></div>
					</div>
				</main>
			</div>
			<footer class="smart-admin-footer"><?php echo esc_html(sprintf(__('Smart eCommerce Store v%s', 'smart-ecommerce-store'), SES_PRODUCT_VERSION)); ?></footer>
		</div>
		<?php
	}

	private static function products() {
		$refresh = isset($_GET['refresh']) && check_admin_referer('ses_refresh');
		$catalog = SES_Catalog::get($refresh);
		return is_wp_error($catalog) ? $catalog : SES_Products::enrich($catalog);
	}

	public static function dashboard_content() {
		$products = self::products();
		if (is_wp_error($products)) { self::error($products); return; }
		$installed = count(array_filter($products, static function ($product) { return $product['installed']; }));
		?>
		<section class="smart-dash-stats" aria-label="<?php esc_attr_e('Stato dello Store', 'smart-ecommerce-store'); ?>">
			<div class="smart-dash-stat-card"><span class="smart-dash-stat-icon"><span class="dashicons dashicons-store"></span></span><span class="smart-dash-stat-text"><span class="smart-dash-stat-label"><?php esc_html_e('Prodotti disponibili', 'smart-ecommerce-store'); ?></span><strong class="smart-dash-stat-value"><?php echo esc_html(count($products)); ?></strong></span></div>
			<div class="smart-dash-stat-card"><span class="smart-dash-stat-icon"><span class="dashicons dashicons-admin-plugins"></span></span><span class="smart-dash-stat-text"><span class="smart-dash-stat-label"><?php esc_html_e('Installati', 'smart-ecommerce-store'); ?></span><strong class="smart-dash-stat-value"><?php echo esc_html($installed); ?></strong></span></div>
			<div class="smart-dash-stat-card"><span class="smart-dash-stat-icon"><span class="dashicons dashicons-shield-alt"></span></span><span class="smart-dash-stat-text"><span class="smart-dash-stat-label"><?php esc_html_e('Catalogo', 'smart-ecommerce-store'); ?></span><strong class="smart-dash-stat-value is-ok"><?php esc_html_e('Verificato', 'smart-ecommerce-store'); ?></strong></span></div>
		</section>
		<section class="ses-journey"><h2><?php esc_html_e('Come procedere', 'smart-ecommerce-store'); ?></h2><ol><li><strong><?php esc_html_e('Scegli', 'smart-ecommerce-store'); ?></strong><span><?php esc_html_e('Confronta Free e Premium.', 'smart-ecommerce-store'); ?></span></li><li><strong><?php esc_html_e('Installa', 'smart-ecommerce-store'); ?></strong><span><?php esc_html_e('Lo Store verifica la licenza senza attivarla e installa il pacchetto.', 'smart-ecommerce-store'); ?></span></li><li><strong><?php esc_html_e('Attiva nel prodotto', 'smart-ecommerce-store'); ?></strong><span><?php esc_html_e('Apri il plugin installato e completa l’attivazione Freemius.', 'smart-ecommerce-store'); ?></span></li></ol></section>
		<?php self::product_list($products, false); ?>
		<?php
	}

	public static function catalog_content() {
		$products = self::products();
		if (is_wp_error($products)) { self::error($products); return; }
		self::product_list($products, false);
	}

	public static function my_products_content() {
		$products = self::products();
		if (is_wp_error($products)) { self::error($products); return; }
		$mine = array_filter($products, static function ($product) { return $product['installed']; });
		if (!$mine) { echo '<div class="ses-empty"><span class="dashicons dashicons-admin-plugins"></span><h2>' . esc_html__('Non hai ancora installato prodotti', 'smart-ecommerce-store') . '</h2><p>' . esc_html__('Vai allo Store, scegli un prodotto e segui l’azione consigliata.', 'smart-ecommerce-store') . '</p><a class="button button-primary" href="' . esc_url(add_query_arg('page', self::MENU_SLUG, admin_url('admin.php'))) . '">' . esc_html__('Apri lo Store', 'smart-ecommerce-store') . '</a></div>'; return; }
		self::product_list($mine, true);
	}

	private static function product_list(array $products, $owned) {
		if (!$products) { echo '<div class="ses-empty"><h2>' . esc_html__('Nessun prodotto disponibile', 'smart-ecommerce-store') . '</h2></div>'; return; }
		$groups = array('plugin' => array(__('Plugin', 'smart-ecommerce-store'), 'dashicons-admin-plugins'), 'theme' => array(__('Temi', 'smart-ecommerce-store'), 'dashicons-admin-appearance'));
		foreach ($groups as $type => $group) {
			$items = array_filter($products, static function ($product) use ($type) { return $type === ($product['type'] ?? 'plugin'); });
			echo '<section class="ses-products"><header class="ses-products-heading"><span class="dashicons ' . esc_attr($group[1]) . '" aria-hidden="true"></span><div><h2>' . esc_html($group[0]) . '</h2><p>' . esc_html($owned ? __('Prodotti installati in questa categoria.', 'smart-ecommerce-store') : ('theme' === $type ? __('Aspetto e struttura del sito.', 'smart-ecommerce-store') : __('Funzioni e strumenti per WordPress.', 'smart-ecommerce-store'))) . '</p></div><strong>' . esc_html((string) count($items)) . '</strong></header>';
			if (!$items) { echo '<p class="ses-empty-section">' . esc_html__('Nessun prodotto disponibile in questa sezione.', 'smart-ecommerce-store') . '</p></section>'; continue; }
			foreach ($items as $product) { self::product_row($product); }
			echo '</section>';
		}
	}

	private static function product_row(array $product) {
		$status = $product['active'] ? __('Attivo', 'smart-ecommerce-store') : ($product['installed'] ? __('Installato', 'smart-ecommerce-store') : __('Disponibile', 'smart-ecommerce-store'));
		?>
		<article class="ses-product-row">
			<div class="ses-product-main"><?php if ($product['icon_url']) : ?><img src="<?php echo esc_url($product['icon_url']); ?>" alt="" width="56" height="56"><?php else : ?><span class="dashicons dashicons-admin-plugins"></span><?php endif; ?><div><div class="ses-product-title"><h3><?php echo esc_html($product['name']); ?></h3><span class="ses-badge"><?php echo esc_html($status); ?></span><span class="ses-edition"><?php echo 'freemius' === $product['channel'] ? esc_html__('Premium', 'smart-ecommerce-store') : esc_html__('Free', 'smart-ecommerce-store'); ?></span></div><p><?php echo wp_kses_post($product['description']); ?></p><small><?php echo esc_html(sprintf(__('Versione %s', 'smart-ecommerce-store'), $product['version'] ?: '—')); ?></small><?php if ('freemius' === $product['channel'] && $product['installed']) { self::license_panel($product); } ?></div></div>
			<div class="ses-product-actions">
				<?php if ('freemius' === $product['channel'] && !$product['installed']) : self::premium_form($product); ?><a class="button" href="<?php echo esc_url($product['checkout_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Acquista Premium', 'smart-ecommerce-store'); ?></a>
				<?php elseif (!$product['installed']) : self::action_form($product, 'install', 'theme' === ($product['type'] ?? 'plugin') ? __('Installa tema', 'smart-ecommerce-store') : __('Installa Free', 'smart-ecommerce-store')); ?>
				<?php elseif (!empty($product['update_available'])) : self::action_form($product, 'update', __('Aggiorna', 'smart-ecommerce-store')); ?>
				<?php elseif (!$product['active']) : self::action_form($product, 'activate', __('Attiva', 'smart-ecommerce-store')); ?>
				<?php else : ?><span class="ses-ready"><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e('Pronto all’uso', 'smart-ecommerce-store'); ?></span><?php endif; ?>
				<?php if ('freemius' === $product['channel'] && $product['active']) : self::action_form($product, 'reset_freemius', __('Ripristina collegamento', 'smart-ecommerce-store'), false); endif; ?>
				<?php if ($product['homepage']) : ?><a class="button ses-details-button" href="<?php echo esc_url($product['homepage']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Dettagli', 'smart-ecommerce-store'); ?></a><?php endif; ?>
			</div>
		</article>
		<?php
	}

	private static function license_panel(array $product) {
		$license = SES_Licenses::status($product['slug']);
		if (is_wp_error($license)) {
			echo '<div class="ses-license ses-license-help"><strong>' . esc_html__('Licenza Premium', 'smart-ecommerce-store') . '</strong><span>' . esc_html($license->get_error_message()) . '</span></div>';
			return;
		}
		$active = 'active' === $license['status'];
		$expiration = $license['expiration'] ? strtotime($license['expiration']) : false;
		?>
		<div class="ses-license">
			<div><span><?php esc_html_e('Licenza', 'smart-ecommerce-store'); ?></span><strong class="<?php echo $active ? 'is-ok' : 'is-alert'; ?>"><?php echo $active ? esc_html__('Verificata', 'smart-ecommerce-store') : esc_html__('Scaduta o non valida', 'smart-ecommerce-store'); ?></strong></div>
			<div><span><?php esc_html_e('Scadenza', 'smart-ecommerce-store'); ?></span><strong><?php echo $expiration ? esc_html(wp_date(get_option('date_format'), $expiration)) : esc_html__('Non indicata', 'smart-ecommerce-store'); ?></strong></div>
			<div><span><?php esc_html_e('Sito autorizzato', 'smart-ecommerce-store'); ?></span><strong><?php echo esc_html($license['site_url']); ?></strong></div>
			<div class="ses-license-actions"><?php if ($license['renew_url']) : ?><a href="<?php echo esc_url($license['renew_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Rinnova', 'smart-ecommerce-store'); ?></a><?php endif; ?><?php if ($license['portal_url']) : ?><a href="<?php echo esc_url($license['portal_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Portale cliente', 'smart-ecommerce-store'); ?></a><?php endif; ?></div>
		</div>
		<?php
	}

	private static function premium_form(array $product) {
		$field_id = 'ses-license-' . sanitize_html_class($product['slug']);
		?><form class="ses-premium-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ses_product_action"><input type="hidden" name="product_action" value="premium_install"><input type="hidden" name="slug" value="<?php echo esc_attr($product['slug']); ?>"><?php wp_nonce_field('ses_product_' . $product['slug']); ?><label for="<?php echo esc_attr($field_id); ?>"><?php esc_html_e('Hai già una licenza?', 'smart-ecommerce-store'); ?></label><div><input id="<?php echo esc_attr($field_id); ?>" name="license_key" type="password" required minlength="20" autocomplete="off" placeholder="<?php esc_attr_e('Inserisci la chiave Freemius', 'smart-ecommerce-store'); ?>"><button class="button button-primary" type="submit"><?php esc_html_e('Verifica e installa', 'smart-ecommerce-store'); ?></button></div><p class="description"><?php esc_html_e('Lo Store verifica il diritto al download senza creare un’installazione Freemius. La chiave non viene salvata; dopo l’installazione la inserirai nel plugin.', 'smart-ecommerce-store'); ?></p></form><?php
	}

	private static function action_form(array $product, $action, $label, $primary = true) {
		?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ses_product_action"><input type="hidden" name="product_action" value="<?php echo esc_attr($action); ?>"><input type="hidden" name="slug" value="<?php echo esc_attr($product['slug']); ?>"><?php wp_nonce_field('ses_product_' . $product['slug']); ?><button class="button<?php echo $primary ? ' button-primary' : ''; ?>" type="submit"><?php echo esc_html($label); ?></button></form><?php
	}

	private static function error($error) { echo '<section class="ses-state ses-state-error" role="alert"><span class="dashicons dashicons-shield" aria-hidden="true"></span><div><h2>' . esc_html__('Catalogo temporaneamente non disponibile', 'smart-ecommerce-store') . '</h2><p>' . esc_html($error->get_error_message()) . '</p><p>' . esc_html__('Per sicurezza acquisti e installazioni restano disabilitati. I prodotti già installati non vengono modificati.', 'smart-ecommerce-store') . '</p><p><a class="button" href="' . esc_url(wp_nonce_url(add_query_arg(array('page' => self::MENU_SLUG, 'refresh' => 1), admin_url('admin.php')), 'ses_refresh')) . '">' . esc_html__('Riprova aggiornamento', 'smart-ecommerce-store') . '</a></p></div></section>'; }
	private static function notice() { if (empty($_GET['ses_message'])) { return; } $status = sanitize_key(wp_unslash($_GET['ses_status'] ?? 'error')); $message = sanitize_text_field(wp_unslash($_GET['ses_message'])); printf('<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', 'success' === $status ? 'success' : 'error', esc_html($message)); }
	public static function capture_notices_start() {
		if (!self::is_plugin_screen() || null !== self::$notice_buffer_level) { return; }
		self::$notice_buffer_level = ob_get_level();
		ob_start();
	}

	public static function capture_notices_end() {
		if (!self::is_plugin_screen() || null === self::$notice_buffer_level) { return; }
		$expected_level = self::$notice_buffer_level + 1;
		if ($expected_level === ob_get_level()) {
			self::$captured_notices .= (string) ob_get_clean();
		}
		self::$notice_buffer_level = null;
	}

	private static function is_plugin_screen() {
		$page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
		return self::MENU_SLUG === $page || 0 === strpos($page, 'ses-');
	}
}
