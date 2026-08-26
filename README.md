# Smart eCommerce Store

Public WordPress catalog for Smart eCommerce products approved for distribution.

## UI contract

The WordPress administration follows `SMART-ADMIN-PANEL-DESIGN-SYSTEM.md` v2.0.0 from the shared `smart-admin-design-system` repository.

The Store consumes the signed repository catalog but accepts only entries with an explicit public visibility and a supported public distribution channel. Missing visibility defaults to `internal` and is never rendered.

## Initial scope

- Free installation through WordPress.org.
- Premium purchase through Freemius checkout.
- Premium license verification and authorized installation through the Smart eCommerce server proxy, without creating a Freemius install.
- Site activation remains exclusively managed by the installed product's Freemius SDK.
- Clear installed and active states.
- No internal catalog, development tools or repository administration.

## Support

- Product: https://smartecommerce.it/smart-ecommerce-store/
- Email: adv@smartecommerce.it
