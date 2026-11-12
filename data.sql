CREATE DATABASE mini_youtube;
USE mini_youtube;

CREATE TABLE videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    videoId VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    publishedAt DATETIME,
    channelTitle VARCHAR(255),
    searchQuery VARCHAR(255),
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);