-- Create database
CREATE DATABASE IF NOT EXISTS taal_tourist_db;
USE taal_tourist_db;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Points of Interest table
CREATE TABLE points_of_interest (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category ENUM('attraction', 'restaurant', 'accommodation', 'cultural', 'historical') NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    address TEXT,
    contact_info VARCHAR(100),
    opening_hours VARCHAR(100),
    price_range VARCHAR(50),
    rating DECIMAL(2,1) DEFAULT 0,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reviews table
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    poi_id INT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (poi_id) REFERENCES points_of_interest(id) ON DELETE CASCADE
);

-- User favorites table
CREATE TABLE user_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    poi_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (poi_id) REFERENCES points_of_interest(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, poi_id)
);

-- Chat history table for AI interactions
CREATE TABLE chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT NOT NULL,
    response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample data
INSERT INTO points_of_interest (name, description, category, latitude, longitude, address, contact_info, opening_hours, price_range, rating, image_url) VALUES
('Taal Volcano', 'Active volcano located in the middle of Taal Lake', 'attraction', 14.0021, 120.9937, 'Taal Lake, Batangas', '+63 917 123 4567', '6:00 AM - 5:00 PM', '₱200-500', 4.5, 'images/taal-volcano.jpg'),
('Basilica of St. Martin de Tours', 'Historic Catholic church, one of the largest in Asia', 'cultural', 13.8781, 121.1537, 'Taal, Batangas', '+63 43 408 0032', '6:00 AM - 6:00 PM', 'Free', 4.8, 'images/basilica.jpg'),
('Taal Heritage Town', 'Well-preserved Spanish colonial architecture', 'historical', 13.8781, 121.1537, 'Taal, Batangas', '+63 43 408 1234', '8:00 AM - 5:00 PM', '₱50-100', 4.3, 'images/heritage-town.jpg'),
('Lomi King', 'Famous local restaurant serving traditional Batangas lomi', 'restaurant', 13.8790, 121.1540, 'Taal, Batangas', '+63 917 234 5678', '7:00 AM - 9:00 PM', '₱100-300', 4.2, 'images/lomi-king.jpg'),
('Villa Tortuga', 'Boutique hotel with lake view', 'accommodation', 13.8800, 121.1550, 'Taal, Batangas', '+63 43 408 5678', '24/7', '₱3000-8000', 4.6, 'images/villa-tortuga.jpg');

-- Insert sample user
INSERT INTO users (username, email, password, full_name, phone) VALUES
('admin', 'admin@taal-tourist.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', '+63 917 000 0000');
