set -eu

test_database="${MYSQL_TEST_DATABASE:-uyuni_tour_test}"
case "$test_database" in
    *[!A-Za-z0-9_]*|'')
        echo "MYSQL_TEST_DATABASE must contain only letters, digits, and underscores." >&2
        exit 1
        ;;
esac

if [ "$test_database" = "$MYSQL_DATABASE" ]; then
    echo "The test database must differ from MYSQL_DATABASE." >&2
    exit 1
fi

MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root <<SQL
CREATE DATABASE IF NOT EXISTS \`$test_database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON \`$test_database\`.* TO '$MYSQL_USER'@'%';
SQL
