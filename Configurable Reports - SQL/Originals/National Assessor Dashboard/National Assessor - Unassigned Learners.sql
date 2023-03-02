SELECT DISTINCT
concat('<a target="_new" href="%%WWWROOT%%/user/editadvanced.php?id=',u.id,'">Assign group</a>') AS 'Assign group',
uid.data AS 'Group',
prog.fullname,
CONCAT('<span class="accesshide" >', CAST(u.timecreated as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(u.timecreated),'%d %M %Y %H:%i:%s')) AS 'Account created',
IF (u.lastaccess = 0,'<span class="accesshide" >000000000000</span>never', CONCAT('<span class="accesshide" >', CAST(u.lastaccess as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(u.lastaccess),'%d %M %Y %H:%i:%s'))) AS 'LMS Last Access',
IF((UNIX_TIMESTAMP() - u.lastaccess)/86400 > 10000, 'never', ROUND((UNIX_TIMESTAMP() - u.lastaccess)/86400,0)) As 'Days since last access',
concat('<a target="_new" href = "%%WWWROOT%%/user/profile.php?id=', CAST(u.id AS CHAR), '">',u.firstname, ' ', u.lastname, '</a><hr><a target="_new" href = "https://crm.mito.org.nz/MITOCRM/main.aspx?etc=2&extraqs=formid%3d85b5f7f3-ac5a-4beb-95da-2fb3e6b50f38&id=%7b',u.idnumber,'%7d&pagetype=entityrecord">',u.username,'</a><hr>', u.phone1) As 'Learner',
concat('<a target="_new" href="%%WWWROOT%%/report/log/user.php?id=',u.id,'&course=1&mode=all">Log</a>') AS 'Log',
concat('<a target="_new" href="%%WWWROOT%%/totara/plan/index.php?userid=',u.id,'&c=course">Goal courses</a>') AS 'Goal courses',
concat('<a target="_new" href="%%WWWROOT%%/report/completion/user.php?id=',u.id,'&course=1">Course progress</a>') AS 'Course progress'

FROM prefix_course AS c  
JOIN prefix_course_categories AS cc ON cc.id = c.category
JOIN prefix_enrol AS en ON en.courseid = c.id
JOIN prefix_user_enrolments AS ue ON ue.enrolid = en.id
JOIN prefix_user AS u ON ue.userid = u.id
LEFT JOIN prefix_prog_user_assignment AS progua ON progua.userid = u.id 
LEFT JOIN prefix_prog AS prog ON prog.id = progua.programid
LEFT Join prefix_user_info_data uid ON uid.userid = u.id
LEFT JOIN prefix_user_info_field uif ON uid.fieldid = uif.id AND uif.shortname = 'assessorgroup'

WHERE
u.suspended = 0
AND prog.fullname IS NOT NULL
AND prog.fullname != 'Business (First Line Management) (Level 4)'
AND prog.fullname != 'First Line Management - Online'
AND prog.fullname != 'Coachbuilding (Level 3) 2020-01'
/*AND prog.fullname != 'Collision Repair Non-structural Repair (Level 3-4)'
AND prog.fullname != 'Collision Repair (Non-structural Repair) (Level 3-4) 2019-01'
AND prog.fullname != 'Collision Repair (Non-structural Repair) (Level 3-4) 2021-01'
AND prog.fullname != 'Collision Repair (Non-structural Repair) (Level 4) 2018-01'
AND prog.fullname != 'Collision Repair Non-Structural Repair (Level 4) (direct entry) 2021-01'
AND prog.fullname != 'Collision Repair (Level 3-4)'
AND prog.fullname != 'Collision Repair (Level 3 & 4)'*/
AND prog.fullname NOT LIKE 'Refinishing%'
AND prog.fullname != 'StartUp Accelerate (Automotive Engineering) 2019-01'
AND prog.fullname != 'StartUp Accelerate (Collision Repair and Refinishing) 2019-01'	
/*AND prog.fullname != 'Automotive Refinishing (Level 3-4) 2018-01'
AND prog.fullname != 'Collision Repair and Automotive Refinishing (Level 3)'
AND prog.fullname != 'Collision Repair and Automotive Refinishing (Level 3) 2018-01'
AND prog.fullname != 'Automotive Refinishing (Level 3-4) 2021-01'
AND prog.fullname != 'Automotive Refinishing (Level 4) 2018-01'*/
AND prog.fullname NOT LIKE 'Collision Repair%'
AND prog.fullname NOT LIKE 'Automotive Refinishing%'
AND prog.fullname NOT LIKE 'Automotive Machining%'
AND prog.fullname NOT LIKE 'Introduction to Automotive Engineering Micro-credential'
AND prog.fullname NOT LIKE 'StartUp%'
AND u.username != '773290@mymitonz.org.nz'  AND u.username != '773294@mymitonz.org.nz' AND u.username != '773288@mymitonz.org.nz'
AND (cc.name = 'NZ Cert Auto engineering' OR cc.name = 'Motorcycle')
AND (uid.data IS NULL OR uid.data = 'Not assigned')

ORDER BY uid.data, u.firstname ASC, u.lastaccess DESC