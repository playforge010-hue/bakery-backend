# Local demo catalog

Run `php artisan db:seed` to add the storefront demo catalog. The seeder is safe to
run more than once and does not create customers, orders, payments, reviews, or
dashboard activity. It leaves existing business settings and catalog entries in
place.

Create an owner account separately with `php artisan admin:create-owner`. The
local development login autofill is read from the frontend's ignored `.env.local`
file and is never included in production builds.

The catalog includes sample bakery products, variants, flavours, add-ons,
customization options, delivery hours, delivery slots, distance fees, and one
homepage banner. Product names and prices are demonstration values; confirm the
bakery's actual menu and pricing in the admin before taking real orders.

## Image sources

Images are stored in `database/seeders/assets/demo-catalog` and copied to Laravel
public storage when the seeder runs. They are local assets at runtime, so the
storefront does not depend on an image CDN being available.

- `chocolate-berry-cake.jpg` — [Unsplash chocolate cake with strawberries](https://unsplash.com/photos/a-chocolate-cake-with-strawberries-on-top-HmssZtWo_f8)
- `croissant-basket.jpg` — [Unsplash basket of croissants and pastries](https://unsplash.com/photos/a-basket-filled-with-croissants-and-other-pastries-_ZrT9thWCxU)
- `cupcake-display.jpg` — [Unsplash cupcake display](https://unsplash.com/es/fotos/una-variedad-de-cupcakes-y-muffins-estan-en-exhibicion-yI_jyVkbFVM)
- `birthday-cake.jpg` — [Unsplash birthday cake](https://unsplash.com/photos/a-birthday-cake-with-lit-candles-sitting-on-a-table-zzTjty6f_1Y)
- `bakery-pastries.jpg` — [Unsplash bakery display of cupcakes and cookies](https://unsplash.com/es/fotos/magdalenas-en-mostrador-de-vidrio-transparente-1Gv_4RcljOE)
- `chocolate-cake.jpg` — [Unsplash chocolate cake photograph](https://images.unsplash.com/photo-1606313564200-e75d5e30476c)
- `samosa.jpg` — [Unsplash samosa photo](https://images.unsplash.com/photo-1601050690597-df0568f70950)

Unsplash permits most personal and commercial uses under its
[license](https://help.unsplash.com/en/articles/2612315-can-i-use-unsplash-images-for-personal-or-commercial-projects).
These are illustrative stock photographs, not verified photos of Dwarkesh
Bakers' actual products. Replace them with the bakery's own product photography
and confirm the seeded menu, prices, contact number, and pin location before
launch.
