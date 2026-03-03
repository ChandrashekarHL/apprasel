# ERP Dashboard Integration Guide

## Overview

This document provides instructions for integrating the Faculty Appraisal System with the main ERP dashboard at `https://erp.gmit.info/fms/dashboard.php`.

## Integration Button

Add the following button to the teacher's dashboard page to enable SSO access to the appraisal system:

### HTML Code

```html
<!-- Faculty Appraisal System Button -->
<a href="https://apprasel.gmit.info/sso_login.php" 
   class="btn btn-primary" 
   style="margin: 10px;">
    <i class="fas fa-chart-line"></i> Faculty Workload & Appraisal
</a>
```

### Alternative with Icon

```html
<div class="dashboard-card">
    <a href="https://apprasel.gmit.info/sso_login.php" class="dashboard-link">
        <div class="icon-wrapper">
            <i class="fas fa-clipboard-check fa-3x"></i>
        </div>
        <h4>Faculty Appraisal</h4>
        <p>Access your workload and performance dashboard</p>
    </a>
</div>
```

## How It Works

### Authentication Flow

1. **User Login**: Teacher logs into main ERP system
2. **Session Created**: ERP creates session with `$_SESSION['emp_id']`
3. **Click Button**: Teacher clicks "Faculty Workload & Appraisal" button
4. **SSO Redirect**: Browser navigates to `sso_login.php`
5. **Session Check**: SSO endpoint reads `$_SESSION['emp_id']` from shared session
6. **API Call**: System fetches full user profile from ERP API
7. **Data Sync**: User data synced to `ad_faculty_users` table
8. **Auto-Login**: Local session created with user credentials
9. **Dashboard**: User redirected to appropriate dashboard based on role

### Session Requirements

**Critical**: The session must be shared between both domains. Ensure:

- Both systems use the same session name
- Session cookies are accessible across subdomains
- `session.cookie_domain` is set appropriately in `php.ini`

Example `php.ini` configuration:
```ini
session.cookie_domain = ".gmit.info"
session.cookie_path = "/"
```

## API Endpoint Required

The SSO implementation requires a new API endpoint on the ERP side:

**Endpoint**: `https://erp.gmit.info/api/fwaems/get_user_by_id.php`

### Request Format

```json
{
    "emp_id": "EMP12345"
}
```

### Response Format (Success)

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
            "USER_GROUP": "FACULTY",
            "PHOTO": "[{\"name\":\"photo_url.jpg\"}]"
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

### Response Format (Error)

```json
{
    "status": "error",
    "message": "Employee not found"
}
```

## Testing the Integration

### 1. Test SSO Flow

1. Login to main ERP system as a teacher
2. Verify `$_SESSION['emp_id']` is set (check with `var_dump($_SESSION)`)
3. Click the "Faculty Appraisal" button
4. Verify automatic login to appraisal system
5. Check that correct dashboard is displayed

### 2. Test Error Handling

**Missing Session**:
- Access `sso_login.php` directly without ERP login
- Should redirect to login page with error message

**Invalid Employee ID**:
- Manually set invalid `$_SESSION['emp_id']`
- Should show error message on login page

### 3. Verify Data Sync

Check database after SSO login:

```sql
-- Verify user was created/updated
SELECT * FROM ad_faculty_users WHERE emp_id = 'EMP12345';

-- Check subject mapping
SELECT * FROM ad_academic_source WHERE faculty_id = (
    SELECT id FROM ad_faculty_users WHERE emp_id = 'EMP12345'
);
```

## Troubleshooting

### Issue: "SSO session not found"

**Cause**: Session not shared between domains

**Solution**:
1. Check `session.cookie_domain` in `php.ini`
2. Ensure both systems use same session name
3. Verify cookies are being sent in browser (check DevTools)

### Issue: "Failed to fetch user profile"

**Cause**: ERP API endpoint not available or returning error

**Solution**:
1. Verify API endpoint exists: `https://erp.gmit.info/api/fwaems/get_user_by_id.php`
2. Test API manually with cURL or Postman
3. Check API logs for errors

### Issue: User redirected to wrong dashboard

**Cause**: Role not properly synced from ERP

**Solution**:
1. Check `USER_GROUP` value in ERP API response
2. Verify role mapping logic in `sso_login.php`
3. Manually update role in database if needed:
   ```sql
   UPDATE ad_faculty_users SET role = 'Admin' WHERE emp_id = 'EMP12345';
   ```

## Security Considerations

1. **HTTPS Required**: Both systems should use HTTPS in production
2. **Session Security**: Use secure session settings:
   ```php
   ini_set('session.cookie_secure', 1);
   ini_set('session.cookie_httponly', 1);
   ini_set('session.cookie_samesite', 'Lax');
   ```
3. **Input Validation**: Employee ID is validated before API call
4. **Error Logging**: All errors are logged for security monitoring

## Support

For issues or questions, contact the development team.
