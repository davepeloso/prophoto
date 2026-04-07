## Installation & Build

- [ ] Handle `dist/` assets for installer

  - Current behavior:
    - `bash vendor/prophoto/ingest/install.sh` runs `php artisan vendor:publish --tag=ingest-assets --force`.
    - When `dist/` is missing in the package root, vendor:publish fails with:
      `Can't locate path: </path/to/prophoto-ingest/src/../dist>`.

  - Root cause:
    - `dist/` (compiled frontend bundle) is not present when installing from a fresh clone.

  - Desired behavior:
    - Document required build step:
      "After cloning `prophoto-ingest`, run `npm install && npm run build` to generate `dist/` before testing installs."
    - Installer should warn clearly when `dist/` is missing instead of failing hard.
    - Service provider should only register the `ingest-assets` publish tag when `dist/` exists.

    - [ ] Document APP_URL requirement for ingest routes

  - Current behavior:
    - If Laravel's `APP_URL` does not match the host used in the browser (e.g. app is accessed via `http://pears.test` but `APP_URL` is `http://localhost:8000`), the ingest panel can show `ERR_CONNECTION_REFUSED` for some requests.

  - Root cause:
    - Ingest uses Laravel's `APP_URL` (and/or route helpers based on it) to generate URLs. When this doesn't match the actual domain, assets/API calls point to a non-listening host.

  - Desired behavior:
    - Document in README:
      - After installing, ensure `.env` has `APP_URL` set to the domain you use to access the app (e.g. `http://pears.test`).
    - (Optional) Add troubleshooting note: “If the ingest panel loads but network requests show `ERR_CONNECTION_REFUSED`, verify that `APP_URL` matches the URL in your browser.”