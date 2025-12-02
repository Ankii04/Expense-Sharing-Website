import mysql.connector
import sys

# Railway MySQL connection details - using the same credentials
config = {
    'host': 'shortline.proxy.rlwy.net',
    'port': 10730,
    'user': 'root',
    'password': 'tlhIyJDkgXJDdACPLRlClIzEhDnAtTmD',
    'database': 'railway'
}

try:
    print("Testing connection to Railway MySQL...")
    print(f"Host: {config['host']}")
    print(f"Port: {config['port']}")
    print(f"Database: {config['database']}")
    print(f"User: {config['user']}")
    print()
    
    conn = mysql.connector.connect(**config)
    cursor = conn.cursor()
    
    # Test query
    cursor.execute("SHOW TABLES")
    tables = cursor.fetchall()
    
    print("✅ Connection successful!")
    print(f"\nTables in database ({len(tables)}):")
    for table in tables:
        print(f"  - {table[0]}")
    
    # Check if test user exists
    cursor.execute("SELECT COUNT(*) FROM users")
    user_count = cursor.fetchone()[0]
    print(f"\nUsers in database: {user_count}")
    
    cursor.close()
    conn.close()
    
except mysql.connector.Error as err:
    print(f"❌ Connection failed!")
    print(f"Error: {err}")
    print(f"\nError code: {err.errno}")
    print(f"SQL state: {err.sqlstate}")
    sys.exit(1)
