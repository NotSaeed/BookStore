-- SQL to test database schema after migration
-- Run this to verify all column names are correct

SELECT 
    book_id,
    title,
    author,
    price,
    stock_quantity,
    category,
    description,
    isbn,
    cover_image,
    genre,
    is_public,
    is_featured,
    status,
    created_at,
    updated_at
FROM seller_books 
LIMIT 5;

-- Check if any old column names still exist (should return errors)
SELECT book_title FROM seller_books LIMIT 1; -- Should fail
SELECT book_author FROM seller_books LIMIT 1; -- Should fail  
SELECT book_price FROM seller_books LIMIT 1; -- Should fail
SELECT book_stock FROM seller_books LIMIT 1; -- Should fail
