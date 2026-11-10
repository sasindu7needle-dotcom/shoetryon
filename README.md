# ShopAR – Virtual Shoe Try-On

ShopAR is a Laravel web app that lets shoppers try shoes on virtually in the browser. It uses the
[DeepAR Web SDK](https://docs.deepar.ai/) foot tracking to place a 3D shoe on the user's feet in
real time through their camera. No app install is needed.

## How it works

1. The `/tryon` page lists the shoes defined in `config/shoes.php`.
2. DeepAR opens the device camera and tracks the user's feet.
3. Tapping a shoe loads its DeepAR effect (`.deepar` file) and renders it on the tracked feet.

Shoes whose `.deepar` file is missing are shown greyed out as "effect missing".

## Phone support

The try-on needs HTTPS, camera access, WebGL 2 and WebAssembly. That covers Safari and other browsers on
iOS 15 or later, and current Chrome, Samsung Internet, Firefox and Edge on Android. The page checks for these
up front and explains what to do when something is missing:

- **Camera blocked:** shows where to allow it (Safari's aA menu on iPhone, the address bar icon on Android) and a
  **Try again** button. Coming back to the page after allowing it retries on its own.
- **Camera busy or missing:** says so, with **Try again**.
- **In-app browsers** (Instagram, Facebook, TikTok, Android web views): these often can't use the camera, so the
  page asks to open it in Safari or Chrome and offers **Copy link**.
- **Old browser or WebGL 2 turned off:** asks to update the browser.

The page opens the back camera itself and hands the video to DeepAR. It releases the camera when the page goes to
the background (switching apps, locking the screen) and starts it again on return, restarts it if another app
takes it, and keeps the screen awake while it runs. On phones the camera view is capped in height so the shoe strip
stays on screen. DeepAR crops the camera image to fill the view, so the feet need to be near the middle.

## Tech stack

- Laravel 13 (PHP 8.3+), Blade
- Vite and Tailwind CSS 4
- DeepAR Web SDK v5 (`deepar` on npm), self-hosted under `public/vendor/deepar`
- Docker and Render for deployment

## Project layout

| Path | Purpose |
| --- | --- |
| `app/Http/Controllers/TryOnController.php` | Builds the shoe list and renders the page |
| `config/shoes.php` | Shoe catalog (name, GLB, thumbnail, DeepAR effect path) |
| `resources/views/tryon.blade.php` | Try-on page |
| `resources/js/tryon.js` | DeepAR initialization and effect switching |
| `public/models/samples/` | Source GLB models and thumbnails |
| `public/effects/shoes/` | DeepAR effect files (`<shoe-id>.deepar`) |
| `scripts/copy-deepar.mjs` | Copies the SDK from `node_modules` to `public/vendor/deepar` on `npm install` |

## Requirements

- PHP 8.3+, Composer
- Node.js 20+ and npm
- A DeepAR license key: create a **Web app** at [developer.deepar.ai](https://developer.deepar.ai/) and add
  your domains (`localhost` for development)
- Camera access, which needs `localhost` or HTTPS

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Set your key in `.env`:

```
DEEPAR_LICENSE_KEY=your_key_here
```

Run the app:

```bash
php artisan serve
npm run dev
```

Open <http://localhost:8000/tryon>.

## Adding shoes

DeepAR cannot load `.glb` files directly at runtime. Each shoe must be a `.deepar` effect:

1. Create the effect, either:
   - in **DeepAR Studio**: import the GLB using the shoe try-on template, place it under the `model_left` /
     `model_right` nodes and export it, or
   - with `scripts/build-deepar-effect.py`, for a GLB with one mesh and one PBR material (base colour,
     normal and metal-roughness textures) like the samples. It builds the effect from the Studio-exported
     street runner, so no Studio is needed:

     ```bash
     pip install pillow
     python scripts/build-deepar-effect.py public/effects/shoes/street-runner.deepar \
         public/models/samples/<shoe-id>.glb public/effects/shoes/<shoe-id>.deepar <shoe-id>
     ```

     Add `--leg-occluder` for boots (DeepAR's own leg occluder ends about 20 cm above the sole, so the
     inside of a tall shaft would show). `--set` overrides a material uniform: `u_specularIntensity` and
     `u_envMapIntensity` tone down reflections on a dark glossy shoe, and `u_roughness` multiplies the
     GLB's roughness. The ballet flat and rain boot were built this way:

     ```bash
     python scripts/build-deepar-effect.py public/effects/shoes/street-runner.deepar \
         public/models/samples/ballet-flat.glb public/effects/shoes/ballet-flat.deepar ballet-flat \
         --set u_specularIntensity=0.5 --set u_envMapIntensity=0.6 --set u_roughness=0.33
     python scripts/build-deepar-effect.py public/effects/shoes/street-runner.deepar \
         public/models/samples/rain-boot.glb public/effects/shoes/rain-boot.deepar rain-boot \
         --leg-occluder --set u_envMapIntensity=0.8 --set u_specularIntensity=0.4
     ```

     DeepAR's shader gamma-decodes every texture, so the script stores the normal and metal-roughness
     maps gamma-encoded, as Studio does. Copied as they are in the GLB, the normals tilt by about 38
     degrees and the roughness drops to a mirror finish, which showed as one shoe of the pair looking
     washed-out grey.
2. Save it as `public/effects/shoes/<shoe-id>.deepar`.
3. Add (or edit) the entry in `config/shoes.php` with the same `id`, plus the `glb` and `thumb` paths.
4. Add a `fit` for the shoe (see below). Effects from the script keep the GLB's own axes and size (metres,
   toe along `+x`), so they all start from `rotation` `[0, -90, 0]` and a `scale` of about 90.

The shoe becomes selectable on the page as soon as its `.deepar` file exists.

### Fitting a shoe to the foot

Studio keeps the GLB at its original size (metres), while DeepAR's foot space is about 20 units long, so an
unscaled shoe ends up a few millimetres long and hidden inside the foot. Studio also imports GLB axes
inconsistently. The try-on page therefore sets each shoe's `scale`, `rotation` (Euler degrees) and
`position` on the template's `model_left` and `model_right` nodes after the effect loads, using the
`fit` values in `config/shoes.php`.

The `fit` is for the right foot. The page mirrors it onto the left foot (negative `x` scale and `x`
position, negated `y`/`z` rotation), the same way the template mirrors the left shoe and the left foot
occluder. Don't set `model_left` separately, or the left foot ends up wearing a right shoe.

The page also moves DeepAR's foot occluder 0.6 units forward (`OCCLUDER_SHIFT` in `resources/js/tryon.js`).
Phone tests showed the tracked occluder sitting about that far behind real feet: shoes fitted to it sat
too far back, and shoes moved forward on their own got their heel counter hidden inside the occluder.
All fits are measured against the moved occluder.

For a new shoe, start from the closest existing `fit` and adjust:

1. Rotate until the shoe is upright with the toe pointing along `+z`.
2. Scale and move along `z` until the GLB's `tryOnFit` heel and toe anchors sit on the occluder's heel
   (`z ≈ -14.8`) and toes (`z ≈ 5.9`). Add up to 6% scale for tracking jitter. Don't scale a narrow shoe
   up to stop the foot poking through it: that moves its toe and opening forward, and the real toes show
   inside the shoe. Use a `stretch` instead (see below).
3. Move along `x` to centre the shoe on the foot. The occluder sits about 0.5 units towards the big toe
   (`+x`), so a shoe centred on `x = 0` leaves the big toe showing.
4. Move along `y` until the outsole is on the occluder's sole (`y ≈ -1.9`).
5. Keep the back of the heel counter just behind the occluder's heel. Inside it, the occluder hides the
   counter; much further back, the insole shows behind the heel.

The foot must not poke out of the top or sides of the shoe anywhere except the collar, or the camera
image shows through as a hole in the shoe. Check the whole length of both side walls, not just the toe
cap: a low shoe like the ballet flat has little wall to spare.

If the shoe is narrower than DeepAR's foot, no uniform `scale` keeps the foot inside its walls without
making it far too long. Add a `stretch` of `[width, height, length]` factors to the `fit`. The page sets it
on the `shopar_shoe_*_origin_compensator` nodes, the parents of `model_*`, which have no rotation, so it
scales along the foot's own axes whatever the shoe's `rotation` is. The ballet flat sits at its anchor
scale with `[1.26, 1.06, 1]`: DeepAR's blunt toes need the width at the front of its pointed toe box,
and its heel needs it at the inner side of the heel counter. When it was scaled up 7% instead, its
opening reached over most of DeepAR's toes and phones showed bare toes inside it. The rain boot uses
`[1.05, 1, 1]` and sits 0.2 towards the outside, because DeepAR's leg column came out through the outer
back of its shaft above the ankle. For a boot, check the shaft against the leg column as well as the foot.

> DeepAR has moved shoe try-on to its separate ShopAR product. Foot tracking still works in the web SDK
> version used here, so `deepar` is pinned to exactly `5.6.22` in `package.json`. Test before upgrading it.

## Deploying to Render

The repo includes a `Dockerfile` and `render.yaml`.

1. In Render, choose **New → Blueprint** and select this repository.
2. Set the environment variables when prompted:
   - `APP_KEY`: generate with `php artisan key:generate --show` and paste the full `base64:...` value
   - `DEEPAR_LICENSE_KEY`: your DeepAR key
3. Add the `*.onrender.com` domain to the allowed domains of your DeepAR Web app.
4. Make sure the `.deepar` files are committed in `public/effects/shoes/`.

The container listens on Render's `$PORT`, runs migrations on start and trusts Render's proxy so URLs are
generated over HTTPS. The default setup uses SQLite with file sessions and cache, which are reset on each
deploy on the free plan. The app stores no persistent data.

## License

Released under the terms in [LICENSE](LICENSE).
