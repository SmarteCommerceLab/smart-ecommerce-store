<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Admin {
	const MENU_SLUG = 'smart-ecommerce-store';
	private static $captured_notices = '';

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
				'slug' => 'ses-system',
				'page_title' => __('Sistema', 'smart-ecommerce-store'),
				'menu_title' => __('Sistema', 'smart-ecommerce-store'),
				'capability' => 'install_plugins',
				'callback' => array(__CLASS__, 'render_system'),
				'icon' => 'dashicons-shield',
				'description' => __('Catalogo, runtime e diagnostica tecnica.', 'smart-ecommerce-store'),
			),
		);
	}

	public static function menu() {
		add_menu_page(
			__('Smart eCommerce Store', 'smart-ecommerce-store'),
			__('Smart Store', 'smart-ecommerce-store'),
			'install_plugins', self::MENU_SLUG,
			array(__CLASS__, 'render_dashboard'), 'dashicons-store', 58
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
		wp_enqueue_style('ses-catalog', SES_URL . 'assets/catalog.css', array('ses-admin'), SES_VERSION);
		if (false !== strpos((string) $hook, 'ses-system')) {
			wp_enqueue_style('ses-system', SES_URL . 'assets/system.css', array('ses-admin'), SES_VERSION);
		}
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

	public static function render_system() {
		self::template(__('Sistema', 'smart-ecommerce-store'), __('Controlla catalogo, runtime e informazioni utili per l assistenza.', 'smart-ecommerce-store'), array(__CLASS__, 'system_content'));
	}

	private static function template($title, $description, $callback) {
		if (!current_user_can('install_plugins')) { wp_die(esc_html__('Permessi insufficienti.', 'smart-ecommerce-store')); }
		$current = sanitize_key(wp_unslash($_GET['page'] ?? self::MENU_SLUG));
		$scheme = get_user_option('admin_color');
		global $_wp_admin_css_colors;
		$header = isset($_wp_admin_css_colors[$scheme]->colors[0]) ? $_wp_admin_css_colors[$scheme]->colors[0] : '#1d2327';
		$pages = array_merge(array(array(
			'slug' => self::MENU_SLUG, 'menu_title' => __('Store', 'smart-ecommerce-store'),
			'icon' => 'dashicons-store', 'description' => __('Percorso di acquisto guidato.', 'smart-ecommerce-store'),
		)), self::get_subpages());
		?>
		<div class="wrap smart-admin-wrap" style="--smart-header:<?php echo esc_attr($header); ?>">
			<header class="smart-admin-header">
				<div class="smart-admin-header-brand"><span class="smart-admin-logo">Se</span><div><strong><?php echo esc_html(SES_PRODUCT_NAME); ?></strong><small><?php esc_html_e('Catalogo ufficiale Smart eCommerce', 'smart-ecommerce-store'); ?></small></div></div>
				<div class="smart-admin-header-actions"><span>v<?php echo esc_html(SES_PRODUCT_VERSION); ?></span></div>
			</header>
			<div class="smart-admin-shell">
				<aside class="smart-admin-sidebar"><nav class="smart-admin-nav" aria-label="<?php esc_attr_e('Navigazione Store', 'smart-ecommerce-store'); ?>">
					<?php foreach ($pages as $page) : $active = $current === $page['slug']; ?>
						<a href="<?php echo esc_url(add_query_arg('page', $page['slug'], admin_url('admin.php'))); ?>" class="<?php echo $active ? 'is-active' : ''; ?>" <?php echo $active ? 'aria-current="page"' : ''; ?>><span class="dashicons <?php echo esc_attr($page['icon']); ?>"></span><span><strong><?php echo esc_html($page['menu_title']); ?></strong><small><?php echo esc_html($page['description']); ?></small></span></a>
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
		<section class="ses-status-strip" aria-label="<?php esc_attr_e('Stato dello Store', 'smart-ecommerce-store'); ?>">
			<div><span><?php esc_html_e('Prodotti disponibili', 'smart-ecommerce-store'); ?></span><strong><?php echo esc_html(count($products)); ?></strong></div>
			<div><span><?php esc_html_e('Installati', 'smart-ecommerce-store'); ?></span><strong><?php echo esc_html($installed); ?></strong></div>
			<div><span><?php esc_html_e('Catalogo', 'smart-ecommerce-store'); ?></span><strong class="is-ok"><?php esc_html_e('Verificato', 'smart-ecommerce-store'); ?></strong></div>
		</section>
		<section class="ses-journey"><h2><?php esc_html_e('Come procedere', 'smart-ecommerce-store'); ?></h2><ol><li><strong><?php esc_html_e('Scegli', 'smart-ecommerce-store'); ?></strong><span><?php esc_html_e('Confronta Free e Premium.', 'smart-ecommerce-store'); ?></span></li><li><strong><?php esc_html_e('Ottieni', 'smart-ecommerce-store'); ?></strong><span><?php esc_html_e('Installa la Free o acquista la Premium.', 'smart-ecommerce-store'); ?></span></li><li><strong><?php esc_html_e('Attiva', 'smart-ecommerce-store'); ?></strong><span><?php esc_html_e('Inserisci la licenza e completa l’installazione.', 'smart-ecommerce-store'); ?></span></li></ol></section>
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

	public static function system_content() {
		$catalog = SES_Catalog::get(false);
		$catalog_ok = !is_wp_error($catalog);
		$checks = array(
			array(__('Catalogo firmato', 'smart-ecommerce-store'), $catalog_ok ? __('Verificato', 'smart-ecommerce-store') : __('Da verificare', 'smart-ecommerce-store'), $catalog_ok),
			array(__('OpenSSL', 'smart-ecommerce-store'), function_exists('openssl_verify') ? __('Disponibile', 'smart-ecommerce-store') : __('Mancante', 'smart-ecommerce-store'), function_exists('openssl_verify')),
			array(__('HTTPS', 'smart-ecommerce-store'), is_ssl() ? __('Attivo', 'smart-ecommerce-store') : __('Non attivo', 'smart-ecommerce-store'), is_ssl()),
			array(__('WordPress cron', 'smart-ecommerce-store'), defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? __('Disattivato', 'smart-ecommerce-store') : __('Disponibile', 'smart-ecommerce-store'), !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON)),
		);
		$report = array(
			'product' => SES_PRODUCT_NAME,
			'version' => SES_VERSION,
			'wordpress' => get_bloginfo('version'),
			'php' => PHP_VERSION,
			'multisite' => is_multisite(),
			'https' => is_ssl(),
			'catalog' => array('endpoint' => SES_CATALOG_URL, 'status' => $catalog_ok ? 'ok' : 'error'),
		);
		?>
		<section class="ses-system-hero <?php echo $catalog_ok ? 'is-ok' : 'is-warning'; ?>"><span class="dashicons <?php echo $catalog_ok ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span><div><h2><?php echo esc_html($catalog_ok ? __('Sistema operativo', 'smart-ecommerce-store') : __('Richiede attenzione', 'smart-ecommerce-store')); ?></h2><p><?php echo esc_html($catalog_ok ? __('Il catalogo ufficiale e verificato e lo Store puo operare.', 'smart-ecommerce-store') : $catalog->get_error_message()); ?></p></div></section>
		<div class="ses-system-list"><?php foreach ($checks as $check) : ?><div><span class="dashicons <?php echo esc_attr($check[2] ? 'dashicons-yes-alt' : 'dashicons-warning'); ?>" aria-hidden="true"></span><span><strong><?php echo esc_html($check[0]); ?></strong><small><?php echo esc_html($check[1]); ?></small></span><b><?php echo esc_html($check[2] ? __('OK', 'smart-ecommerce-store') : __('Verifica', 'smart-ecommerce-store')); ?></b></div><?php endforeach; ?></div>
		<section class="ses-support-report"><h2><?php esc_html_e('Report tecnico per l assistenza', 'smart-ecommerce-store'); ?></h2><p><?php esc_html_e('Non contiene credenziali, chiavi di licenza o contenuti del sito.', 'smart-ecommerce-store'); ?></p><textarea readonly rows="10"><?php echo esc_textarea(wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></section>
		<?php
	}

	private static function product_list(array $products, $owned) {
		if (!$products) { echo '<div class="ses-empty"><h2>' . esc_html__('Nessun prodotto disponibile', 'smart-ecommerce-store') . '</h2></div>'; return; }
		$groups = array(
			'plugin' => array(__('Plugin', 'smart-ecommerce-store'), 'dashicons-admin-plugins'),
			'theme' => array(__('Temi', 'smart-ecommerce-store'), 'dashicons-admin-appearance'),
		);
		foreach ($groups as $type => $group) {
			$items = array_filter($products, static function ($product) use ($type) { return $type === ($product['type'] ?? 'plugin'); });
			echo '<section class="ses-products ses-products-' . esc_attr($type) . '"><header class="ses-products-heading"><span class="dashicons ' . esc_attr($group[1]) . '" aria-hidden="true"></span><div><h2>' . esc_html($group[0]) . '</h2><p>' . esc_html($owned ? __('Prodotti installati in questa categoria.', 'smart-ecommerce-store') : ('theme' === $type ? __('Aspetto e struttura del sito.', 'smart-ecommerce-store') : __('Funzioni e strumenti per WordPress.', 'smart-ecommerce-store'))) . '</p></div><strong>' . esc_html((string) count($items)) . '</strong></header>';
			if (!$items) { echo '<p class="ses-empty-section">' . esc_html__('Nessun prodotto disponibile in questa sezione.', 'smart-ecommerce-store') . '</p></section>'; continue; }
			foreach ($items as $product) { self::product_row($product); }
			echo '</section>';
		}
	}

	private static function product_row(array $product) {
		$status = $product['active'] ? __('Attivo', 'smart-ecommerce-store') : ($product['installed'] ? __('Installato', 'smart-ecommerce-store') : __('Disponibile', 'smart-ecommerce-store'));
		?>
		<article class="ses-product-row">
			<div class="ses-product-main"><?php if ($product['icon_url']) : ?><img src="<?php echo esc_url($product['icon_url']); ?>" alt="" width="56" height="56"><?php else : ?><span class="dashicons <?php echo esc_attr('theme' === ($product['type'] ?? 'plugin') ? 'dashicons-admin-appearance' : 'dashicons-admin-plugins'); ?>"></span><?php endif; ?><div><div class="ses-product-title"><h3><?php echo esc_html($product['name']); ?></h3><span class="ses-badge"><?php echo esc_html($status); ?></span><span class="ses-edition"><?php echo 'freemius' === $product['channel'] ? esc_html__('Premium', 'smart-ecommerce-store') : esc_html__('Free', 'smart-ecommerce-store'); ?></span></div><p><?php echo wp_kses_post($product['description']); ?></p><small><?php echo esc_html(sprintf(__('Versione %s', 'smart-ecommerce-store'), $product['version'] ?: '—')); ?></small></div></div>
			<div class="ses-product-actions">
				<?php if ('freemius' === $product['channel'] && !$product['installed']) : self::premium_form($product); ?><a class="button" href="<?php echo esc_url($product['checkout_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Acquista Premium', 'smart-ecommerce-store'); ?></a>
				<?php elseif (!$product['installed']) : self::action_form($product, 'install', 'theme' === ($product['type'] ?? 'plugin') ? __('Installa tema', 'smart-ecommerce-store') : __('Installa Free', 'smart-ecommerce-store')); ?>
				<?php elseif (!empty($product['update_available'])) : self::action_form($product, 'update', __('Aggiorna', 'smart-ecommerce-store')); ?>
				<?php elseif (!$product['active']) : self::action_form($product, 'activate', __('Attiva', 'smart-ecommerce-store')); ?>
				<?php else : ?><span class="ses-ready"><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e('Pronto all’uso', 'smart-ecommerce-store'); ?></span><?php endif; ?>
				<?php if ($product['homepage']) : ?><a class="button button-link" href="<?php echo esc_url($product['homepage']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Dettagli', 'smart-ecommerce-store'); ?></a><?php endif; ?>
			</div>
		</article>
		<?php
	}

	private static function premium_form(array $product) {
		$field_id = 'ses-license-' . sanitize_html_class($product['slug']);
		?><form class="ses-premium-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ses_product_action"><input type="hidden" name="product_action" value="premium_install"><input type="hidden" name="slug" value="<?php echo esc_attr($product['slug']); ?>"><?php wp_nonce_field('ses_product_' . $product['slug']); ?><label for="<?php echo esc_attr($field_id); ?>"><?php esc_html_e('Hai già una licenza?', 'smart-ecommerce-store'); ?></label><div><input id="<?php echo esc_attr($field_id); ?>" name="license_key" type="password" required minlength="20" autocomplete="off" placeholder="<?php esc_attr_e('Inserisci la chiave Freemius', 'smart-ecommerce-store'); ?>"><button class="button button-primary" type="submit"><?php esc_html_e('Verifica e installa', 'smart-ecommerce-store'); ?></button></div><p class="description"><?php esc_html_e('La chiave viene usata per autorizzare il download e non viene salvata dallo Store.', 'smart-ecommerce-store'); ?></p></form><?php
	}

	private static function action_form(array $product, $action, $label) {
		?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ses_product_action"><input type="hidden" name="product_action" value="<?php echo esc_attr($action); ?>"><input type="hidden" name="slug" value="<?php echo esc_attr($product['slug']); ?>"><?php wp_nonce_field('ses_product_' . $product['slug']); ?><button class="button button-primary" type="submit"><?php echo esc_html($label); ?></button></form><?php
	}

	private static function error($error) { echo '<div class="notice notice-error inline"><p>' . esc_html($error->get_error_message()) . '</p></div>'; }
	private static function notice() { if (empty($_GET['ses_message'])) { return; } $status = sanitize_key(wp_unslash($_GET['ses_status'] ?? 'error')); $message = sanitize_text_field(wp_unslash($_GET['ses_message'])); printf('<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', 'success' === $status ? 'success' : 'error', esc_html($message)); }
	public static function capture_notices_start() { if (self::$captured_notices === '') { ob_start(); } }
	public static function capture_notices_end() { if (ob_get_level()) { self::$captured_notices .= (string) ob_get_clean(); } }
}
