# API Endpoint Setup Guide

## Overview

This guide explains how to set up the `get_user_by_id.php` API endpoint on your ERP server.

## File Location

The API file should be placed at:
```
https://erp.gmit.info/api/fwaems/get_user_by_id.php
```

Physical server path (example):
```
/var/www/html/api/fwaems/get_user_by_id.php
```

## What You Need

### 1. Database Information

You need to know your ERP database structure. Specifically:

**User/Staff Table:**
- Table name (e.g., `staff_master`, `users`, `employees`)
- Column names for:
  - Employee ID
  - Username
  - Full Name
  - Designation
  - Department
  - School
  - Mobile Number
  - Email
  - User Group (Faculty/HOD/Dean)
  - Photo

**Subject Allocation Table:**
- Table name (e.g., `subject_allocation`, `course_assignments`)
- Column names for:
  - Employee ID
  - Academic Year
  - Programme (UG/PG)
  - Course (BTECH/MTECH)
  - Semester
  - Section
  - Subject Code
  - Subject Name
  - Season (ODD/EVEN)

### 2. Database Connection

You need access to your ERP database. The API needs:
- Database host
- Database name
- Database username
- Database password

## Setup Steps

### Step 1: Get the Template File

I've created a template file for you: `api_template_get_user_by_id.php`

This file is in your appraisal system directory as a reference.

### Step 2: Customize the Template

Open the template and update these sections:

#### A. Database Connection (Line ~50)

**Option 1**: Use existing config file
```php
require_once '../config/database.php';
```

**Option 2**: Direct connection
```php
$host = 'localhost';
$dbname = 'your_erp_database';
$username = 'your_db_user';
$password = 'your_db_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}
```

#### B. User Query (Line ~70)

Update table and column names to match your database:

```php
$userQuery = "
    SELECT 
        YOUR_EMP_ID_COLUMN as ID,
        YOUR_USERNAME_COLUMN as USER_NAME,
        YOUR_NAME_COLUMN as NAME,
        YOUR_DESIGNATION_COLUMN as DESIGNATION,
        YOUR_DEPT_COLUMN as DISCIPLINE,
        YOUR_SCHOOL_COLUMN as SCHOOL,
        YOUR_MOBILE_COLUMN as MOBILE_NO,
        YOUR_EMAIL_COLUMN as EMAIL,
        YOUR_USER_GROUP_COLUMN as USER_GROUP,
        YOUR_PHOTO_COLUMN as PHOTO
    FROM YOUR_STAFF_TABLE 
    WHERE YOUR_EMP_ID_COLUMN = :emp_id
    LIMIT 1
";
```

**Example** (if your table is `tbl_staff`):
```php
$userQuery = "
    SELECT 
        emp_id as ID,
        username as USER_NAME,
        full_name as NAME,
        designation as DESIGNATION,
        department as DISCIPLINE,
        school as SCHOOL,
        mobile as MOBILE_NO,
        email as EMAIL,
        user_type as USER_GROUP,
        photo_path as PHOTO
    FROM tbl_staff 
    WHERE emp_id = :emp_id
    LIMIT 1
";
```

#### C. Subject Query (Line ~95)

Update table and column names:

```php
$subjectQuery = "
    SELECT 
        YOUR_ACADEMIC_YEAR_COLUMN as ACADEMIC_YEAR,
        YOUR_PROGRAMME_COLUMN as PROGRAMME,
        YOUR_COURSE_COLUMN as COURSE,
        YOUR_SEMESTER_COLUMN as SEM,
        YOUR_SECTION_COLUMN as SECTION,
        YOUR_SUBJECT_CODE_COLUMN as SUBJECT_CODE,
        YOUR_SUBJECT_NAME_COLUMN as SUBJECT,
        YOUR_SEASON_COLUMN as SEASON
    FROM YOUR_SUBJECT_TABLE
    WHERE YOUR_EMP_ID_COLUMN = :emp_id
    AND YOUR_ACADEMIC_YEAR_COLUMN = :current_year
    ORDER BY SEM, SECTION
";
```

### Step 3: Upload to ERP Server

1. Copy the customized file to your ERP server
2. Place it at: `/path/to/erp/api/fwaems/get_user_by_id.php`
3. Set proper permissions:
   ```bash
   chmod 644 get_user_by_id.php
   chown www-data:www-data get_user_by_id.php
   ```

### Step 4: Test the API

#### Using cURL (Command Line)

```bash
curl -X POST https://erp.gmit.info/api/fwaems/get_user_by_id.php \
  -H "Content-Type: application/json" \
  -d '{"emp_id":"EMP12345"}'
```

#### Using Postman

1. Method: POST
2. URL: `https://erp.gmit.info/api/fwaems/get_user_by_id.php`
3. Headers: `Content-Type: application/json`
4. Body (raw JSON):
   ```json
   {
       "emp_id": "EMP12345"
   }
   ```

#### Expected Response

**Success:**
```json
{
    "status": "success",
    "data": {
        "user": {
            "ID": "EMP12345",
            "USER_NAME": "john.doe",
            "NAME": "John Doe",
            "DESIGNATION": "Assistant Professor",
            "DISCIPLINE": "Computer Science",
            "SCHOOL": "School of Engineering",
            "MOBILE_NO": "9876543210",
            "EMAIL": "john@example.com",
            "USER_GROUP": "FACULTY",
            "PHOTO": "photos/john.jpg"
        },
        "subject_mapping": [
            {
                "ACADEMIC_YEAR": "2025-26",
                "PROGRAMME": "UG",
                "COURSE": "BTECH",
                "SEM": "3",
                "SECTION": "A",
                "SUBJECT_CODE": "CS301",
                "SUBJECT": "Data Structures",
                "SEASON": "ODD"
            }
        ]
    }
}
```

**Error:**
```json
{
    "status": "error",
    "message": "Employee not found"
}
```

## Common Issues & Solutions

### Issue 1: "Database connection failed"

**Solution**: Check database credentials in the config file

### Issue 2: "Employee not found"

**Solution**: Verify the employee ID exists in your database:
```sql
SELECT * FROM your_staff_table WHERE emp_id = 'EMP12345';
```

### Issue 3: "Column not found"

**Solution**: Check your column names match the query. Run:
```sql
DESCRIBE your_staff_table;
```

### Issue 4: CORS errors

**Solution**: The template includes CORS headers. If still having issues, update line 23:
```php
header('Access-Control-Allow-Origin: https://apprasel.gmit.info');
```

## Security Considerations

1. **Use HTTPS**: Ensure the API is only accessible via HTTPS
2. **Validate Input**: The template includes input validation
3. **Limit Access**: Consider adding authentication/API key if needed
4. **Error Messages**: Don't expose sensitive database details in production

## Need Help?

If you're unsure about your database structure, run these queries on your ERP database:

```sql
-- List all tables
SHOW TABLES;

-- Show structure of staff table (replace with your table name)
DESCRIBE staff_master;

-- Show structure of subject table (replace with your table name)
DESCRIBE subject_allocation;

-- Test query with a known employee ID
SELECT * FROM staff_master WHERE EMP_ID = 'YOUR_TEST_EMP_ID';
```

Send me the output and I can help customize the queries!
