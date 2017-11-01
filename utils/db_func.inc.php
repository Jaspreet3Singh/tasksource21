<?php

require_once 'constants.inc.php';

function get_task_by_id($dbh, $task_id) {
    $statement = 'getting task with given $task_id';
    $query = 'SELECT t.name, t.description, t.postal_code, t.address, t.status, t.bidding_deadline,
              t.start_datetime, t.end_datetime, t.suggested_price, t.category, t.owner_email
              FROM tasks t
              JOIN users u ON t.owner_email = u.email
              WHERE t.id = $1';

    $result = pg_prepare($dbh, $statement, $query);
    $params = array($task_id);
    $result = pg_execute($dbh, $statement, $params);

    if (pg_num_rows($result) === 0) {
        return false;
    }

    $task_array = pg_fetch_assoc($result); // this returns associative array (i.e. dictionary)
    return $task_array;
}

function get_task_array_or_redirect($dbh) {
    if (empty($_GET[TASK_ID])) {
        header('Location: home.php'); // set message
        exit;
    }
    $task_array = get_task_by_id($dbh, $_GET[TASK_ID]);
    if ($task_array === false) {
        header('Location: home.php'); // set message
        exit;
    }
    return $task_array;
}

function get_task_categories($dbh) {
    $statement = 'getting categories';
    $query = 'SELECT tc.name
              FROM task_categories tc';
    $result = pg_prepare($dbh, $statement, $query);
    $params = array();
    $result = pg_execute($dbh, $statement, $params);

    if ($result === false)
        return false;

    $categories = array();
    while ($row = pg_fetch_row($result)) {
        $categories[] = $row[0];
    }

    return $categories;
}

function insert_new_task($dbh, $params) {
    if (count($params) !== 10)
        return false;

    $statement = 'inserting the task into db';
    $query = 'INSERT INTO tasks (name, owner_email, description, category, postal_code, address,
              start_datetime, end_datetime, suggested_price, bidding_deadline)
              VALUES($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)';
    $result = pg_prepare($dbh, $statement, $query);
    $result = pg_execute($dbh, $statement, $params);
    return $result;
}

function update_task($dbh, $params) {
    $statement = 'inserting the task into db';
    $query = 'UPDATE tasks SET name = $1, owner_email = $2, description = $3, category = $4, postal_code = $5, address = $6,
              start_datetime = $7, end_datetime = $8, suggested_price = $9, bidding_deadline = $10
              WHERE id = $11';
    $result = pg_prepare($dbh, $statement, $query);
    $result = pg_execute($dbh, $statement, $params);
    return $result;
}

function get_assigned_user($dbh, $task_id) {
    $statement = 'get the user assigned to task';
    $query = 'SELECT b.bidder_email
              FROM bid_task b
              WHERE b.task_id = $1 AND b.is_winner = TRUE';

    $result = pg_prepare($dbh, $statement, $query);
    $params = array($task_id);
    $result = pg_execute($dbh, $statement, $params);

    if ($result === false)
        return false;
    if (pg_num_rows($result) === 0)
        return false;

    return pg_fetch_array($result)[DB_BIDDER];
}

// =================================== for login/user related ====================================== //

// returns false if database connection fails, 0 if no user, 1 if non-admin user, 2 if admin
function check_user_login($dbh, $user_email, $password) {
    $password_hash = hash('sha256', $password, false);
    $statement = 'selecting user';

    $query = 'SELECT u.is_admin
              FROM users u 
              WHERE u.email = $1 AND u.password_hash = $2';

    $result = pg_prepare($dbh, $statement, $query);
    $params = array($user_email, $password_hash);
    $result = pg_execute($dbh, $statement, $params);
    if ($result === false)
        return false;
    if (pg_num_rows($result) === 0)
        return 0;
    $is_admin = pg_fetch_assoc($result)[ADMIN];
    if ($is_admin === 'f')
        return 1;
    else
        return 2;
}

function insert_new_user($dbh, $params) {
    if (count($params) !== 4)
        return false;

    $statement = 'inserting new user';
    $query = 'INSERT INTO users (email, password_hash, name, phone) VALUES ($1, $2, $3, $4)';
    $result = pg_prepare($dbh, $statement, $query);
    $result = pg_execute($dbh, $statement, $params);
    return $result;
}

function check_user_not_exist($dbh, $email) {
    $statement = 'checking for duplicate user';
    $query = 'SELECT * FROM users WHERE email=$1';
    $result = pg_prepare($dbh, $statement, $query);
    $params = array($email);
    $result = pg_execute($dbh, $statement, $params);
    if ($result === false)
        die('problem with database');

    return pg_numrows($result) === 0;
}

// =================================== for home.php ====================================== //
function get_tasks_in_bidding($dbh, $user_id) {
    $statement = 'get tasks user is currently bidding for';
    $query = 'SELECT t.id, t.name, t.bidding_deadline
              FROM tasks t
              WHERE t.id IN
              (SELECT b.task_id
              FROM bid_task b
              WHERE b.bidder_email = $1
              )
              AND t.bidding_deadline > now() AND t.status = \'open\'
              ORDER BY t.bidding_deadline DESC';
    $result = pg_prepare($dbh, $statement, $query);
    $params = array($user_id);
    $result = pg_execute($dbh, $statement, $params);

    $tasks = array();
    while ($row = pg_fetch_assoc($result)) {
        $bid_time = new DateTime($row[DB_BIDDING_DEADLINE]);
        $bid_time = $bid_time->format('H:i d M Y');
        $row[DB_BIDDING_DEADLINE] = $bid_time;
        $tasks[] = $row;
    }
    return $tasks;
}

function get_tasks_created($dbh, $user_id) {
    $statement = 'get tasks user has created that is in bidding stage or assignment stage';

    // USE UNION to merge bidding_deadline and start_datetime
    $query = 'SELECT t.id, t.name, t.bidding_deadline AS date, t.status
              FROM tasks t
              WHERE t.owner_email = $1
              AND t.bidding_deadline > now() AND t.status = \'open\'
              
              UNION SELECT t2.id, t2.name, t2.start_datetime, t2.status
              FROM tasks t2
              WHERE t2.owner_email = $1
              AND t2.start_datetime > now() AND t2.status = \'bidding_closed\'
              ORDER BY date DESC';

    $result = pg_prepare($dbh, $statement, $query);
    $params = array($user_id);
    $result = pg_execute($dbh, $statement, $params);

    $tasks = array();
    while ($row = pg_fetch_assoc($result)) {
        $bid_time = new DateTime($row[DB_DATE]);
        $bid_time = $bid_time->format('H:i d M Y');
        $row[DB_DATE] = $bid_time;
        $tasks[] = $row;
    }
    return $tasks;
}

function get_tasks_assigned($dbh, $user_id) {
    $statement = 'get tasks assigned to the user and has to be done in the future';
    $query = 'SELECT t.id, t.name, t.start_datetime
              FROM tasks t
              WHERE t.id IN
              (SELECT b.task_id
              FROM bid_task b
              WHERE b.bidder_email = $1 AND is_winner = TRUE)
              AND t.start_datetime > now()
              ORDER BY t.start_datetime DESC';
    $result = pg_prepare($dbh, $statement, $query);
    $params = array($user_id);
    $result = pg_execute($dbh, $statement, $params);

    $tasks = array();
    while ($row = pg_fetch_assoc($result)) {
        $bid_time = new DateTime($row[DB_START_DT]);
        $bid_time = $bid_time->format('H:i d M Y');
        $row[DB_START_DT] = $bid_time;
        $tasks[] = $row;
    }
    return $tasks;
}

function get_tasks_complete($dbh, $user_id) {
    $statement = 'get previous tasks completed/submitted by the user';
    $query = 'SELECT t.id, t.name, t.start_datetime AS date
              FROM tasks t
              WHERE 
              (
              /* task successfully bidded */
              t.id IN
              (SELECT b.task_id
              FROM bid_task b
              WHERE b.bidder_email = $1 AND is_winner = TRUE)
              
              /* or task created by the user for which there exists a s successful bidder*/
              OR (
              t.owner_email = $1
              AND EXISTS (SELECT * FROM
              bid_task b2
              WHERE b2.task_id = t.id AND b2.is_winner = TRUE))
              )
              
              /* either case, it has to be a past task */
              AND t.start_datetime < now()
              ORDER BY t.start_datetime DESC
              ';
    $result = pg_prepare($dbh, $statement, $query);
    $params = array($user_id);
    $result = pg_execute($dbh, $statement, $params);

    $tasks = array();
    while ($row = pg_fetch_assoc($result)) {
        $bid_time = new DateTime($row[DB_DATE]);
        $bid_time = $bid_time->format('H:i d M Y');
        $row[DB_DATE] = $bid_time;
        $tasks[] = $row;
    }
    return $tasks;
}

function get_rating_as_owner($dbh, $user_id) {
    $statement = 'get owner rating';
    $query = 'SELECT AVG(r.rating)
              FROM task_ratings r
              WHERE r.user_email = $1 AND r.role = \'doer\'
              GROUP BY r.user_email';
    $result = pg_prepare($dbh, $statement, $query);
    $params = array($user_id);
    $result = pg_execute($dbh, $statement, $params);
    if ($result === false)
        return false;
    if (pg_num_rows($result) === 0)
        return 'N/A';

    return pg_numrows($result)[DB_RATING];
}

function get_rating_as_doer($dbh, $user_id) {
    // can get rating as well as the denominator (number of ratings given)
    $statement = 'get owner rating';
    $query = 'SELECT r.rating
              FROM doer_avg_rating r 
              WHERE r.user = $1';
    $result = pg_prepare($dbh, $statement, $query);
    $params = array($user_id);
    $result = pg_execute($dbh, $statement, $params);
    if ($result === false)
        return false;
    return pg_numrows($result)[DB_RATING];
}

// =================================== bidding related ====================================== //

function get_bids_and_ratings($dbh, $task_id, $limit) {
    $limit_query = '';
    if ($limit !== false) {
        $limit_query = ' LIMIT $2';
    }
    $statement = 'get all bidding information for $task_id';
    $query = 'SELECT b.bid_amount, b.bid_time, b.bidder_email, avg_rating.avg, avg_rating.sum
                  FROM bid_task b
                  LEFT JOIN 
                  (SELECT AVG(r.rating) AS avg, SUM(r.rating) AS sum, r.user_email
                  FROM task_ratings r 
                  GROUP BY r.user_email) AS avg_rating
                  ON avg_rating.user_email = b.bidder_email
                  WHERE b.task_id = $1
                  ORDER BY b.bid_amount DESC';
    $query .= $limit_query;

    $result = pg_prepare($dbh, $statement, $query);
    $params = array($task_id);
    if ($limit !== false) {
        $params[] = $limit;
    }

    $result = pg_execute($dbh, $statement, $params);

    // copy into array of arrays
    $bids = array();
    while ($row = pg_fetch_assoc($result)) {
        $bid_time = new DateTime($row[DB_BID_TIME]);
        $bid_time = $bid_time->format('H:i d M Y');
        $row[DB_BID_TIME] = $bid_time;
        $bids[] = $row;
    }
    return $bids;
}

function withdraw_bid($dbh, $user_id, $task_id) {
    $statement = 'delete bid';
    $query = 'DELETE FROM bid_task
              WHERE bidder_email = $1 AND task_id = $2';

    $result = pg_prepare($dbh, $statement, $query);
    $params = array($user_id, $task_id);
    $result = pg_execute($dbh, $statement, $params);
    return $result;
}

function bid_for_task($dbh, $user_email, $task_id, $bid_amount) {
    $statement = 'check if bid already exists';
    $query = 'SELECT *
              FROM bid_task WHERE
              bidder_email = $1 AND task_id = $2';
    $result = pg_prepare($dbh, $statement, $query);
    $params = array($user_email, $task_id);
    $result = pg_execute($dbh, $statement, $params);
    if (pg_num_rows($result) !== 0) {
        // update
        $statement = 'update bid amount';
        $query = 'UPDATE bid_task 
                  set bid_amount = $1, bid_time = now()
                  WHERE bidder_email = $2 AND task_id = $3';
        $result = pg_prepare($dbh, $statement, $query);
        $params = array($bid_amount, $user_email, $task_id);
        $result = pg_execute($dbh, $statement, $params);
        return $result;
    } else {
        // insert
        $statement = 'insert new bid for task';
        $query = 'INSERT INTO bid_task (bidder_email, task_id, bid_amount, bid_time) 
                  VALUES ($1, $2, $3, current_timestamp)';
        $result = pg_prepare($dbh, $statement, $query);
        $params = array($user_email, $task_id, $bid_amount);
        $result = pg_execute($dbh, $statement, $params);
        return $result;
    }
}

function find_user_bid_for_task($dbh, $user_email, $task_id) {
    $statement = 'getting user bid for task';
    $query = 'SELECT t.bid_amount
              FROM bid_task t
              WHERE t.bidder_email = $1 AND t.task_id = $2';

    $result = pg_prepare($dbh, $statement, $query);
    $params = array($user_email, $task_id);
    $result = pg_execute($dbh, $statement, $params);

    if (pg_num_rows($result) === 0) {
        return 0;
    } else {
        return pg_fetch_assoc($result)[DB_BID_AMOUNT];
    }
}


