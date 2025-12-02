import mysql.connector

# Railway MySQL connection details
config = {
    'host': 'shortline.proxy.rlwy.net',
    'port': 10730,
    'user': 'root',
    'password': 'tlhIyJDkgXJDdACPLRlClIzEhDnAtTmD',
    'database': 'railway'
}

print("Checking current database state...")
try:
    conn = mysql.connector.connect(**config)
    cursor = conn.cursor()
    
    # Check existing tables
    cursor.execute("SHOW TABLES")
    existing_tables = [table[0] for table in cursor.fetchall()]
    print(f"\nExisting tables ({len(existing_tables)}):")
    for table in existing_tables:
        print(f"  - {table}")
    
    # List of all required tables
    required_tables = [
        'users', 'user_settings', 'groups', 'group_members', 
        'expenses', 'expense_splits', 'expense_attachments',
        'settlements', 'group_invites'
    ]
    
    missing_tables = [t for t in required_tables if t not in existing_tables]
    
    if missing_tables:
        print(f"\nMissing tables ({len(missing_tables)}):")
        for table in missing_tables:
            print(f"  - {table}")
        
        print("\nCreating missing tables...")
        
        # Disable foreign key checks
        cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
        
        # Create missing tables one by one
        if 'groups' in missing_tables:
            print("Creating 'groups' table...")
            cursor.execute("""
                CREATE TABLE `groups` (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (created_by) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            print("✓ 'groups' table created")
        
        if 'group_members' in missing_tables:
            print("Creating 'group_members' table...")
            cursor.execute("""
                CREATE TABLE group_members (
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    role ENUM('admin', 'member') DEFAULT 'member',
                    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (group_id, user_id),
                    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            print("✓ 'group_members' table created")
        
        if 'expenses' in missing_tables:
            print("Creating 'expenses' table...")
            cursor.execute("""
                CREATE TABLE expenses (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    description VARCHAR(255) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    paid_by INT NOT NULL,
                    date DATE NOT NULL,
                    category VARCHAR(50) DEFAULT 'Other',
                    split_type ENUM('equal', 'percentage', 'amount') DEFAULT 'equal',
                    status ENUM('pending', 'settled') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
                    FOREIGN KEY (paid_by) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            print("✓ 'expenses' table created")
        
        if 'expense_attachments' in missing_tables:
            print("Creating 'expense_attachments' table...")
            cursor.execute("""
                CREATE TABLE expense_attachments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    expense_id INT NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    file_type VARCHAR(50),
                    uploaded_by INT NOT NULL,
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
                    FOREIGN KEY (uploaded_by) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            print("✓ 'expense_attachments' table created")
        
        if 'settlements' in missing_tables:
            print("Creating 'settlements' table...")
            cursor.execute("""
                CREATE TABLE settlements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    payer_id INT NOT NULL,
                    receiver_id INT NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    payment_reference VARCHAR(100) DEFAULT NULL,
                    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    completed_at TIMESTAMP NULL,
                    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
                    FOREIGN KEY (payer_id) REFERENCES users(id),
                    FOREIGN KEY (receiver_id) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            print("✓ 'settlements' table created")
        
        if 'group_invites' in missing_tables:
            print("Creating 'group_invites' table...")
            cursor.execute("""
                CREATE TABLE group_invites (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    email VARCHAR(255) NULL,
                    token VARCHAR(64) NOT NULL,
                    invite_code VARCHAR(20) NULL,
                    invited_by INT NOT NULL,
                    status ENUM('pending', 'accepted', 'cancelled', 'active') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 7 DAY),
                    accepted_at TIMESTAMP NULL,
                    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
                    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_token (token),
                    INDEX idx_group_status (group_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            print("✓ 'group_invites' table created")
        
        # Re-enable foreign key checks
        cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
        
        conn.commit()
        print("\n✅ All missing tables created successfully!")
    else:
        print("\n✅ All required tables already exist!")
    
    # Final verification
    cursor.execute("SHOW TABLES")
    final_tables = cursor.fetchall()
    print(f"\nFinal table count: {len(final_tables)}")
    for table in final_tables:
        cursor.execute(f"SELECT COUNT(*) FROM {table[0]}")
        count = cursor.fetchone()[0]
        print(f"  ✓ {table[0]} ({count} rows)")
    
    cursor.close()
    conn.close()
    
except mysql.connector.Error as err:
    print(f"❌ Error: {err}")
    exit(1)
