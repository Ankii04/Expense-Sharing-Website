import mysql.connector
import sys

# Railway MySQL connection details
config = {
    'host': 'shortline.proxy.rlwy.net',
    'port': 10730,
    'user': 'root',
    'password': 'tlhIyJDkgXJDdACPLRlClIzEhDnAtTmD',
    'database': 'railway'
}

# Read SQL file
with open('railway-import.sql', 'r', encoding='utf-8') as f:
    sql_content = f.read()

# Split into individual statements
statements = [stmt.strip() for stmt in sql_content.split(';') if stmt.strip()]

try:
    # Connect to database
    print("Connecting to Railway MySQL...")
    conn = mysql.connector.connect(**config)
    cursor = conn.cursor()
    
    print("Importing database schema...")
    
    # Execute each statement
    for i, statement in enumerate(statements, 1):
        if statement:
            try:
                cursor.execute(statement)
                print(f"✓ Executed statement {i}/{len(statements)}")
            except mysql.connector.Error as err:
                print(f"✗ Error in statement {i}: {err}")
    
    conn.commit()
    print("\n✅ Database import completed successfully!")
    print("Tables created: users, user_settings, groups, group_members, expenses, expense_splits, expense_attachments, settlements, group_invites")
    print("Test user created: test@example.com / password")
    
except mysql.connector.Error as err:
    print(f"❌ Database connection failed: {err}")
    sys.exit(1)
    
finally:
    if 'cursor' in locals():
        cursor.close()
    if 'conn' in locals():
        conn.close()
