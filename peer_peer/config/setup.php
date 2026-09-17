<?php
// config/setup.php
require_once __DIR__ . '/database.php';

header('Content-Type: text/plain');

try {
    echo "Connecting to MySQL server...\n";
    // Connect to Host first
    $dsnHost = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $pdo = new PDO($dsnHost, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Creating database `" . DB_NAME . "` if not exists...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `" . DB_NAME . "`;");

    echo "Creating table `users`...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        university VARCHAR(255) DEFAULT NULL,
        department VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        profile_image VARCHAR(255) DEFAULT NULL,
        average_rating DECIMAL(3,2) DEFAULT 0.00,
        role VARCHAR(50) DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    echo "Creating table `textbooks`...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS textbooks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) NOT NULL,
        isbn VARCHAR(50) DEFAULT NULL,
        subject VARCHAR(100) DEFAULT NULL,
        edition VARCHAR(50) DEFAULT NULL,
        `condition` VARCHAR(50) NOT NULL,
        price DECIMAL(10,2) DEFAULT 0.00,
        exchange_type ENUM('sell', 'exchange', 'donate', 'rent') NOT NULL,
        description TEXT DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    echo "Creating table `textbook_images`...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS textbook_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        textbook_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        FOREIGN KEY (textbook_id) REFERENCES textbooks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    echo "Creating table `messages`...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    echo "Creating table `transactions`...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        textbook_id INT NOT NULL,
        buyer_id INT NOT NULL,
        seller_id INT NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (textbook_id) REFERENCES textbooks(id) ON DELETE CASCADE,
        FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    echo "Creating table `reviews`...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT NOT NULL,
        reviewer_id INT NOT NULL,
        reviewee_id INT NOT NULL,
        rating INT CHECK (rating >= 1 AND rating <= 5),
        comment TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    echo "Creating table `reports`...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reported_by INT NOT NULL,
        reported_user INT NOT NULL,
        reason TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reported_user) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    echo "Seeding default users and admin...\n";

    // Insert Admin
    $adminEmail = 'admin@exchange.com';
    $checkAdmin = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkAdmin->execute([$adminEmail]);
    if (!$checkAdmin->fetch()) {
        $adminPass = password_hash('AdminPassword123', PASSWORD_DEFAULT);
        $insertAdmin = $pdo->prepare("INSERT INTO users (name, email, password, university, department, phone, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertAdmin->execute(['System Administrator', $adminEmail, $adminPass, 'Global Exchange Admin', 'IT Support', '1-800-555-0199', 'admin']);
        echo "Admin user created: admin@exchange.com / AdminPassword123\n";
    } else {
        echo "Admin user already exists.\n";
    }

    // Insert Student 1 (John)
    $student1Email = 'john@university.edu';
    $checkS1 = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkS1->execute([$student1Email]);
    if (!$checkS1->fetch()) {
        $passHash = password_hash('Student123', PASSWORD_DEFAULT);
        $insertS = $pdo->prepare("INSERT INTO users (name, email, password, university, department, phone, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertS->execute(['John Doe', $student1Email, $passHash, 'Harvard University', 'Computer Science', '+1-617-555-0101', 'user']);
        $johnId = $pdo->lastInsertId();
        echo "Student 1 created: john@university.edu / Student123\n";
    } else {
        $johnId = $checkS1->fetchColumn();
    }

    // Insert Student 2 (Jane)
    $student2Email = 'jane@university.edu';
    $checkS2 = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkS2->execute([$student2Email]);
    if (!$checkS2->fetch()) {
        $passHash = password_hash('Student123', PASSWORD_DEFAULT);
        $insertS = $pdo->prepare("INSERT INTO users (name, email, password, university, department, phone, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertS->execute(['Jane Smith', $student2Email, $passHash, 'MIT', 'Mathematics', '+1-617-555-0202', 'user']);
        $janeId = $pdo->lastInsertId();
        echo "Student 2 created: jane@university.edu / Student123\n";
    } else {
        $janeId = $checkS2->fetchColumn();
    }

    // Insert Student 3 (Bob)
    $student3Email = 'bob@university.edu';
    $checkS3 = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkS3->execute([$student3Email]);
    if (!$checkS3->fetch()) {
        $passHash = password_hash('Student123', PASSWORD_DEFAULT);
        $insertS = $pdo->prepare("INSERT INTO users (name, email, password, university, department, phone, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertS->execute(['Bob Johnson', $student3Email, $passHash, 'Harvard University', 'Physics', '+1-617-555-0303', 'user']);
        $bobId = $pdo->lastInsertId();
        echo "Student 3 created: bob@university.edu / Student123\n";
    } else {
        $bobId = $checkS3->fetchColumn();
    }

    echo "Seeding textbooks...\n";
    // Check if textbooks exist
    $checkBooks = $pdo->query("SELECT COUNT(*) FROM textbooks");
    if ($checkBooks->fetchColumn() == 0) {
        $insertBook = $pdo->prepare("INSERT INTO textbooks (user_id, title, author, isbn, subject, edition, `condition`, price, exchange_type, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // John's Books
        $insertBook->execute([
            $johnId,
            'Introduction to Algorithms',
            'Thomas H. Cormen, Charles E. Leiserson, Ronald L. Rivest, Clifford Stein',
            '9780262033848',
            'Computer Science',
            '3rd Edition',
            'Good',
            65.00,
            'sell',
            'Classic CLRS textbook, excellent condition with minor highlighting on chapter 3.',
            'available'
        ]);
        
        $insertBook->execute([
            $johnId,
            'Compilers: Principles, Techniques, and Tools',
            'Alfred V. Aho, Monica S. Lam, Ravi Sethi, Jeffrey D. Ullman',
            '9780321486813',
            'Computer Science',
            '2nd Edition',
            'Fair',
            0.00,
            'exchange',
            'The Dragon Book. Cover is slightly worn, looking to exchange for a Web Development book.',
            'available'
        ]);

        // Jane's Books
        $insertBook->execute([
            $janeId,
            'Calculus: Early Transcendentals',
            'James Stewart',
            '9781285741550',
            'Mathematics',
            '8th Edition',
            'New',
            45.00,
            'rent',
            'Like new. Willing to rent for $10 per semester, or sell outright for $45.',
            'available'
        ]);

        $insertBook->execute([
            $janeId,
            'Linear Algebra and Its Applications',
            'David C. Lay',
            '9780321385178',
            'Mathematics',
            '4th Edition',
            'Good',
            0.00,
            'donate',
            'Free to a good home. Clean pages, no markings.',
            'available'
        ]);

        // Bob's Books
        $insertBook->execute([
            $bobId,
            'University Physics with Modern Physics',
            'Hugh D. Young, Roger A. Freedman',
            '9780321973610',
            'Physics',
            '14th Edition',
            'Good',
            50.00,
            'sell',
            'Hardcover version. No missing pages. Used for PHY101/102.',
            'available'
        ]);

        echo "Textbooks seeded successfully!\n";
    } else {
        echo "Textbooks already seeded.\n";
    }

    // Create uploads directories if they don't exist
    $textbooksUploadDir = dirname(__DIR__) . '/uploads/textbooks';
    $profilesUploadDir = dirname(__DIR__) . '/uploads/profiles';
    
    if (!file_exists($textbooksUploadDir)) {
        mkdir($textbooksUploadDir, 0777, true);
    }
    if (!file_exists($profilesUploadDir)) {
        mkdir($profilesUploadDir, 0777, true);
    }

    echo "Uploads directories verified.\n";
    echo "\nDatabase Setup COMPLETED successfully!\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
