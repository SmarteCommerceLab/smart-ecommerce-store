<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Admin {
	public static function register() {
		add_action('admin_menu', array(__CLASS__, 'menu'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
	}

	public static function menu() {
		add_menu_page(
			__('Smart eCommerce Store', 'smart-ecommerce-store'),
			__('Smart Store', 'smart-ecommerce-store'),
			'install_plugins',
			'smart-ecommerce-store',
			array(__CLASS__, 'render'),
			'dashicons-store',
			58
		);
	}

	public static function assets($hook) {
		if ('toplevel_page_smart-ecommerce-store' !== $hook) { return; }
		wp_enqueue_style('ses-admin', SES_URL . 'assets/admin.css', array(), SES_VERSION);
	}

	public static function render() {
		if (!current_user_can('install_plugins')) { return; }
		$catalog = SES_Catalog::get(isset($_GET['refresh']) && check_admin_referer('ses_refresh'));
		?>
		<div class="wrap ses-wrap">
			<header class="ses-hero">
				<div>
					<h1><?php esc_html_e('Smart eCommerce Store', 'smart-ecommerce-store'); ?></h1>
					<p><?php esc_html_e('Plugin verificati, pronti per WordPress e presentati senza elementi interni.', 'smart-ecommerce-store'); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'smart-ecommerce-store', 'refresh' => 1), admin_url('admin.php')), 'ses_refresh')); ?>"><?php esc_html_e('Aggiorna catalogo', 'smart-ecommerce-store'); ?></a>
			</header>

			<?php self::notice(); ?>
			<?php if (is_wp_error($catalog)) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html($catalog->get_error_message()); ?></p></div>
			<?php else : ?>
				<?php $products = SES_Products::enrich($catalog); ?>
				<section class="ses-summary" aria-label="<?php esc_attr_e('Riepilogo catalogo', 'smart-ecommerce-store'); ?>">
					<div><strong><?php echo esc_html(count($products)); ?></strong><span><?php esc_html_e('prodotti pubblici', 'smart-ecommerce-store'); ?></span></div>
					<div><strong><?php echo esc_html(count(array_filter($products, static function ($p) { return $p['installed']; }))); ?></strong><span><?php esc_html_e('installati', 'smart-ecommerce-store'); ?></span></div>
				</section>
				<?php if (!$products) : ?>
					<div class="ses-empty"><span class="dashicons dashicons-store"></span><h2><?php esc_html_e('Nessun prodotto pubblico disponibile', 'smart-ecommerce-store'); ?></h2><p><?php esc_html_e('Il catalogo mostra solo prodotti approvati esplicitamente per la distribuzione.', 'smart-ecommerce-store'); ?></p></div>
				<?php else : ?>
					<div class="ses-grid">
						<?php foreach ($products as $product) { self::card($product); } ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function card(array $product) {
		$status = $product['active'] ? __('Attivo', 'smart-ecommerce-store') : ($product['installed'] ? __('Installato', 'smart-ecommerce-store') : __('Disponibile', 'smart-ecommerce-store'));
		?>
		<article class="ses-card">
			<div class="ses-card__head">
				<?php if ($product['icon_url']) : ?><img src="<?php echo esc_url($product['icon_url']); ?>" alt="" width="48" height="48"><?php else : ?><span class="dashicons dashicons-admin-plugins"></span><?php endif; ?>
				<div><h2><?php echo esc_html($product['name']); ?></h2><span class="ses-badge"><?php echo esc_html($status); ?></span></div>
			</div>
			<p><?php echo wp_kses_post($product['description']); ?></p>
			<div class="ses-meta"><span><?php echo esc_html(sprintf(__('Versione %s', 'smart-ecommerce-store'), $product['version'] ?: '—')); ?></span><span><?php echo 'freemius' === $product['channel'] ? esc_html__('Premium', 'smart-ecommerce-store') : esc_html__('Free', 'smart-ecommerce-store'); ?></span></div>
			<div class="ses-actions">
				<?php if ('freemius' === $product['channel']) : ?>
					<a class="button button-primary" href="<?php echo esc_url($product['checkout_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Acquista Premium', 'smart-ecommerce-store'); ?></a>
				<?php elseif (!$product['installed']) : ?>
					<?php self::action_form($product, 'install', __('Installa', 'smart-ecommerce-store')); ?>
				<?php elseif (!$product['active']) : ?>
					<?php self::action_form($product, 'activate', __('Attiva', 'smart-ecommerce-store')); ?>
				<?php endif; ?>
				<?php if ($product['homepage']) : ?><a class="button" href="<?php echo esc_url($product['homepage']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Dettagli', 'smart-ecommerce-store'); ?></a><?php endif; ?>
			</div>
		</article>
		<?php
	}

	private static function action_form(array $product, $action, $label) {
		?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="ses_product_action">
			<input type="hidden" name="product_action" value="<?php echo esc_attr($action); ?>">
			<input type="hidden" name="slug" value="<?php echo esc_attr($product['slug']); ?>">
			<?php wp_nonce_field('ses_product_' . $product['slug']); ?>
			<button class="button button-primary" type="submit"><?php echo esc_html($label); ?></button>
		</form>
		<?php
	}

	private static function notice() {
		if (empty($_GET['ses_message'])) { return; }
		$status = sanitize_key(wp_unslash($_GET['ses_status'] ?? 'error'));
		$message = sanitize_text_field(wp_unslash($_GET['ses_message']));
		printf('<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', 'success' === $status ? 'success' : 'error', esc_html($message));
	}
}

