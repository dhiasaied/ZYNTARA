#!/usr/bin/env bash
# Runs once, when the PostgreSQL volume is first created.
# 1. Enables pgvector.
# 2. Creates the demo business tables queried by the `database_query` tool (shop + school,
#    the two business activities shown at the end of the reference video).
# 3. Creates a read-only role that can ONLY read those tables.
set -euo pipefail

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-SQL
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE demo_customers (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    country TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE demo_products (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    price NUMERIC(10,2) NOT NULL,
    stock INT NOT NULL
);
CREATE TABLE demo_orders (
    id SERIAL PRIMARY KEY,
    customer_id INT NOT NULL REFERENCES demo_customers(id),
    status TEXT NOT NULL CHECK (status IN ('pending','paid','shipped','delivered','cancelled')),
    total NUMERIC(10,2) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE demo_order_items (
    order_id INT NOT NULL REFERENCES demo_orders(id),
    product_id INT NOT NULL REFERENCES demo_products(id),
    quantity INT NOT NULL,
    unit_price NUMERIC(10,2) NOT NULL,
    PRIMARY KEY (order_id, product_id)
);
CREATE TABLE demo_courses (
    id SERIAL PRIMARY KEY,
    title TEXT NOT NULL,
    level TEXT NOT NULL,
    price NUMERIC(10,2) NOT NULL,
    seats_left INT NOT NULL
);
CREATE TABLE demo_enrollments (
    course_id INT NOT NULL REFERENCES demo_courses(id),
    customer_id INT NOT NULL REFERENCES demo_customers(id),
    progress INT NOT NULL DEFAULT 0,
    enrolled_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (course_id, customer_id)
);

INSERT INTO demo_customers (name, email, country) VALUES
 ('Amina Osman', 'amina@example.com', 'Sudan'),
 ('Youssef Ben Ali', 'youssef@example.com', 'Tunisia'),
 ('Sara Haddad', 'sara@example.com', 'Jordan'),
 ('Lucas Martin', 'lucas@example.com', 'France'),
 ('Fatima Zahra', 'fatima@example.com', 'Morocco');

INSERT INTO demo_products (name, category, price, stock) VALUES
 ('Wireless earbuds', 'Electronics', 59.90, 120),
 ('Smart watch', 'Electronics', 149.00, 35),
 ('Laptop stand', 'Accessories', 39.50, 0),
 ('USB-C hub', 'Accessories', 29.90, 64),
 ('Noise-cancelling headphones', 'Electronics', 219.00, 12);

INSERT INTO demo_orders (customer_id, status, total, created_at) VALUES
 (1, 'delivered', 119.80, now() - interval '12 days'),
 (2, 'shipped', 149.00, now() - interval '3 days'),
 (3, 'paid', 69.40, now() - interval '1 day'),
 (4, 'pending', 219.00, now() - interval '2 hours'),
 (5, 'cancelled', 39.50, now() - interval '20 days');

INSERT INTO demo_order_items VALUES
 (1, 1, 2, 59.90), (2, 2, 1, 149.00), (3, 3, 1, 39.50), (3, 4, 1, 29.90), (4, 5, 1, 219.00), (5, 3, 1, 39.50);

INSERT INTO demo_courses (title, level, price, seats_left) VALUES
 ('Introduction to LLMs', 'Beginner', 0, 200),
 ('Prompt engineering', 'Beginner', 29, 80),
 ('Building RAG applications', 'Intermediate', 79, 25),
 ('Autonomous agents with LangGraph', 'Advanced', 129, 10);

INSERT INTO demo_enrollments (course_id, customer_id, progress) VALUES
 (1, 1, 100), (2, 1, 60), (3, 2, 20), (1, 3, 45), (4, 4, 5);

CREATE ROLE zyntara_readonly LOGIN PASSWORD '${BUSINESS_DB_PASSWORD}' NOSUPERUSER NOCREATEDB NOCREATEROLE;
REVOKE ALL ON SCHEMA public FROM zyntara_readonly;
GRANT CONNECT ON DATABASE "$POSTGRES_DB" TO zyntara_readonly;
GRANT USAGE ON SCHEMA public TO zyntara_readonly;
GRANT SELECT ON demo_customers, demo_products, demo_orders, demo_order_items, demo_courses, demo_enrollments TO zyntara_readonly;
ALTER ROLE zyntara_readonly SET default_transaction_read_only = on;
ALTER ROLE zyntara_readonly SET statement_timeout = '5s';
SQL
