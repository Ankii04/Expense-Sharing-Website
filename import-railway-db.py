import mysql.connector

# Railway MySQL connection details
config = {
    'host': 'shortline.proxy.rlwy.net',
    'port': 10730,
    'user': 'root',
    'password': 'tlhIyJDkgXJDdACPLRlClIzEhDnAtTmD',
    'database': 'railway'
}

print("Connecting to Railway MySQL...")
try:
    conn = mysql.connector.connect(**config)
    cursor = conn.cursor()
    
    # Disable foreign key checks temporarily
    cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
    
    print("Reading SQL file...")
    with open('railway-import.sql', 'r', encoding='utf-8') as f:
        sql_content = f.read()
    
    # Split by semicolons and execute each statement
    statements = [s.strip() for s in sql_content.split(';') if s.strip()]
    
    print(f"Executing {len(statements)} SQL statements...\n")
    success_count = 0
    error_count = 0
    
    for i, statement in enumerate(statements, 1):
        try:
            cursor.execute(statement)
            # Get first few words to show what was executed
            preview = ' '.join(statement.split()[:5])
            print(f"✓ [{i}/{len(statements)}] {preview}...")
            success_count += 1
        except mysql.connector.Error as err:
            preview = ' '.join(statement.split()[:5])
            print(f"✗ [{i}/{len(statements)}] {preview}... ERROR: {err}")
            error_count += 1
    
    # Re-enable foreign key checks
    cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
    
    conn.commit()
    print(f"\n{'='*60}")
    print(f"Import completed: {success_count} successful, {error_count} errors")
    print(f"{'='*60}\n")
    
    # Verify tables were created
    cursor.execute("SHOW TABLES")
    tables = cursor.fetchall()
    print(f"Tables in database ({len(tables)}):")
    for table in tables:
        cursor.execute(f"SELECT COUNT(*) FROM {table[0]}")
        count = cursor.fetchone()[0]
        print(f"  ✓ {table[0]} ({count} rows)")
    
    cursor.close()
    conn.close()
    
    if len(tables) >= 9:
        print("\n✅ All tables created successfully!")
    else:
        print(f"\n⚠️  Warning: Expected 9 tables, but only {len(tables)} were created")
    
except mysql.connector.Error as err:
    print(f"❌ Connection Error: {err}")
    exit(1)
except Exception as e:
    print(f"❌ Error: {e}")
    exit(1)
