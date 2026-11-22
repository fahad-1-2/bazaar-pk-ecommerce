# Bazaar PK — Full-Stack E-Commerce Web Application

A full-stack e-commerce platform built with PHP and MySQL, featuring a responsive storefront, secure user authentication, and a dedicated Admin Control Panel for end-to-end inventory and order management.

## Tech Stack
- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP (Session Management & Server-Side Validation)
- **Database:** MySQL (Relational Schema)

## Key Features
- **User Authentication & Sessions:** Secure user registration, login, and role-based access control separating regular customers from administrators.
- **Dynamic Product Catalog:** Category browsing, product listings, and cart/checkout workflows powered by MySQL queries.
- **Admin Control Panel:** Dedicated management dashboard supporting full CRUD (Create, Read, Update, Delete) operations for products, categories, users, and orders.
- **Database Architecture:** Normalized relational database schema included in the repository (`/database`).

## Screenshots

### Storefront & Product Catalog
<img width="1247" height="580" alt="image" src="https://github.com/user-attachments/assets/e78db094-e2cf-48ef-bde8-6a6f38317771" />


### Main page
<img width="1243" height="567" alt="image" src="https://github.com/user-attachments/assets/88dfdd3f-711d-4daa-b096-e81fde831cbd" />


### Admin Control Panel
<img width="1249" height="569" alt="image" src="https://github.com/user-attachments/assets/0ef39b91-9771-4ccc-b9c7-867659b3f5b7" />


## Local Setup (XAMPP)
1. Clone or download this repository into your `htdocs` folder (`C:\xampp\htdocs\bazaar-pk`).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `phpMyAdmin`, create a database named `bazaar_pk`, and import the `.sql` file from the repository.
4. Update database credentials in the config file if needed and visit `http://localhost/bazaar-pk`.
