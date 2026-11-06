# CampusEvents Hub

```
CampusEvents/
├── Backend/    Laravel API (MySQL, Sanctum)
└── frontend/   React + Vite
    └── src/
        ├── components/   all .jsx files
        ├── css/          one CSS file per section
        ├── utils/        helpers (.ics export)
        └── main.jsx      app state + routing between views
```

## Run the backend
```
cd Backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Create the `campusevents-hubspc` database in phpMyAdmin before running the migrations.
Update the MySQL credentials in `Backend/.env` if they differ from the defaults.
`php artisan migrate --seed` creates the tables and demo data without dropping existing tables.
Demo login: `student@spc.edu.ph` / `password`

## Run the frontend
```
cd frontend
npm install
cp .env.example .env
npm run dev
```
