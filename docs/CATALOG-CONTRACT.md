# Public catalog contract

Smart eCommerce Store reads the signed repository envelope and applies a fail-closed public projection.

Required public product fields:

```json
{
  "type": "plugin",
  "slug": "smartecommerce-selective-cleanup",
  "name": "Smart eCommerce Selective Cleanup",
  "version": "1.6.0",
  "visibility": "public",
  "channel": "wordpress_org",
  "wordpress_org_slug": "smartecommerce-selective-cleanup",
  "homepage": "https://smartecommerce.it/smart-ecommerce-selective-cleanup/",
  "documentation_url": "https://smartecommerce.it/docs/smart-ecommerce-selective-cleanup/"
}
```

Premium entry:

```json
{
  "type": "plugin",
  "slug": "smartecommerce-selective-cleanup-premium",
  "visibility": "commercial",
  "channel": "freemius",
  "checkout_url": "https://checkout.freemius.com/plugin/36313/plan/60094/"
}
```

Visibility values:

- `public`: customer-visible free product.
- `commercial`: customer-visible paid product.
- `internal`: Smart Product Hub only.
- `development`: development environments only.
- `retired`: hidden and unavailable.

An absent or unknown visibility is treated as `internal`.

