/* Create tables */

CREATE TABLE users(
email VARCHAR(64)PRIMARY KEY,
password VARCHAR(20) NOT NULL,
name VARCHAR(50) NOT NULL,
dateOfBirth DATE,
admin BOOLEAN,
phone VARCHAR(50) NOT NULL
);

Create Table create_task(
taskId SERIAL,
ownerEmail VARCHAR(64)REFERENCES users(email),
taskName VARCHAR (64),
taskDesc VARCHAR(255) NOT NULL,
taskCategory VARCHAR(64)NOT NULL,
taskDateAndTime VARCHAR(64) NOT NULL,
status VARCHAR(16) NOT NULL,
winningBidEmail VARCHAR(64),
biddingClose DATE,
Primary Key(taskId, ownerEmail)
); 


CREATE TABLE bid_task(
ownerEmail VARCHAR(64),
taskId BIGINT,
bidderEmail VARCHAR(64)REFERENCES users(email),
bidAmount DECIMAL NOT NULL,
bidStatus VARCHAR(20)NOT NULL,
bidDateTime DATE,
PRIMARY KEY(ownerEmail, taskId, bidderEmail),
FOREIGN KEY(ownerEmail, taskId) REFERENCES create_task(ownerEmail,taskId)
);

Create View bidCount As Select count(b.*), b.taskid,b.owneremail �From bid_task b Group by b.taskid,b.owneremail; 