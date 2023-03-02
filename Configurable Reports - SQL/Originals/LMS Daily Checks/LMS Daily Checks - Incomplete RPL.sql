Select
CONCAT('<a target="_new" href = "https://mitocrm.mito.org.nz/main.aspx?etc=2&extraqs=formid%3d85b5f7f3-ac5a-4beb-95da-2fb3e6b50f38&id=%7b',u.idnumber,'%7d&pagetype=entityrecord">',u.username,'</a>') AS 'CRM link',
CONCAT('<a href = "%%WWWROOT%%/user/profile.php?id=', CAST(u.id AS CHAR), '">',u.firstname, ' ', u.lastname, '</a>') AS 'Learner',
CONCAT('<a target="_new" href = "%%WWWROOT%%/course/view.php?id=', CAST(c.id AS CHAR), '">',CAST(c.fullname AS CHAR),'</a>') AS 'Course', 
CONCAT('<a target="_new" href = "%%WWWROOT%%/mod/quiz/view.php?id=', CAST(cm.id AS CHAR), '">',CAST(quiz.name AS CHAR),'</a>') AS 'Assessment',
cmc.completionstate,
CONCAT('<span class="accesshide" >', CAST(cmc.timecompleted as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(cmc.timecompleted),'%d %M %Y %H:%i:%s')) AS 'Assessment completed',
CONCAT('<a target="_new" href = "%%WWWROOT%%/report/completion/?course=', CAST(c.id AS CHAR), '&page=&silast=', LEFT(u.lastname, 1), '&sifirst=', LEFT(u.firstname, 1) ,'">View report</a>') AS 'View report',
u.id,
gg.usermodified,
gg.overridden,
gg.information,
cccc.rpl

FROM prefix_course_modules_completion cmc 
JOIN prefix_user u ON cmc.userid = u.id
JOIN prefix_course_modules cm ON cmc.coursemoduleid = cm.id
JOIN prefix_course c ON cm.course = c.id
JOIN prefix_modules m ON cm.module = m.id
JOIN prefix_quiz quiz ON quiz.id = cm.instance
JOIN prefix_grade_items gi ON gi.iteminstance = quiz.id
JOIN prefix_grade_grades gg ON gg.itemid = gi.id AND gg.userid = u.id
JOIN prefix_course_completions p ON p.course = c.id AND p.userid = u.id
JOIN prefix_course_completion_criteria ccc ON ccc.moduleinstance = cm.id
JOIN prefix_course_completion_crit_compl cccc ON cccc.userid = u.id AND cccc.criteriaid = ccc.id

Where cmc.completionstate = 0
AND cccc.rpl IS NOT NULL
AND p.timecompleted IS NULL
AND u.username != 'steinmjosh'

ORDER BY u.firstname