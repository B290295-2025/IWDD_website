from database.db_connection import get_connection

def test_db():
    conn = get_connection()
    cursor = conn.cursor()

    cursor.execute("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT)")
    cursor.execute("INSERT INTO users (name) VALUES ('Alice')")

    conn.commit()
    conn.close()

if __name__ == "__main__":
    test_db()
