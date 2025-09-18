CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(255),
    quantity INT NOT NULL
);

-- Insert a new product into the products table
INSERT INTO products (name, price, description, image_path, quantity)
VALUES (?, ?, ?, ?, ?);

