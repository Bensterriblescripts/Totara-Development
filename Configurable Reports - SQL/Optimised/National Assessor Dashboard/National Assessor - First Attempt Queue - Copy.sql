SELECT DISTINCT 
<<<<<<< HEAD:Optimised/01-AE-ALLGROUPS-WRITTENTOMARK-FIRSTATTEMPT-FILTER.sql
/*uid.data as 'datafulltext',*/
SUBSTRING(uid.data, 20, 10) AS 'Group',
/*uid.data as 'profile_assessorgroup',*/
CONCAT('<strong><p ', IF(ROUND((UNIX_TIMESTAMP() - attempts.timefinish)/86400,0) > 5, 'style="color:\ red">Submitted:<br>', ''), IF(ROUND((UNIX_TIMESTAMP() - attempts.timefinish)/86400,0) <= 5 AND ROUND((UNIX_TIMESTAMP() - attempts.timefinish)/86400,0) > 3, 'style="color:\ orange">Submitted:<br>', ''),IF(ROUND((UNIX_TIMESTAMP() - attempts.timefinish)/86400,0) <= 3, 'style="color:\ green">Submitted:<br>', ''),'<span class="accesshide" >', CAST(attempts.timefinish as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish),'%d %M %Y %H:%i:%s'), '<br><br>Expiry:<br>',DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish + 86400*7),'%d %M %Y %H:%i:%s'),'</p></strong>') AS 'Date',
concat('<a target="_new" href = "https://elearning.mito.org.nz/user/profile.php?id=', CAST(u.id AS CHAR), '">',u.firstname, ' ', u.lastname, '</a><hr><a target="_new" href = "https://mitocrm.mito.org.nz/main.aspx?etc=2&extraqs=formid%3d85b5f7f3-ac5a-4beb-95da-2fb3e6b50f38&id=%7b',u.idnumber,'%7d&pagetype=entityrecord">',u.username,'</a><hr>', u.phone1) As 'Learner',
CONCAT(u.department, '<hr>',u.phone2) As Employer,
=======
uid.data AS 'Group',
CONCAT('<strong><p ', IF(ROUND((UNIX_TIMESTAMP() - attempts.timefinish)/86400,0) > 5, 'style="color:\ red">', ''), IF(ROUND((UNIX_TIMESTAMP() - attempts.timefinish)/86400,0) <= 5 AND ROUND((UNIX_TIMESTAMP() - attempts.timefinish)/86400,0) > 3, 'style="color:\ orange">', ''),IF(ROUND((UNIX_TIMESTAMP() - attempts.timefinish)/86400,0) <= 3, 'style="color:\ green">', ''),'<span class="accesshide" >', CAST(attempts.timefinish as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish),'%d %M %Y %H:%i:%s'), '</p></strong>') AS 'Submitted',
concat('<a target="_new" href = "https://elearning.mito.org.nz/user/profile.php?id=', CAST(u.id AS CHAR), '">',u.firstname, ' ', u.lastname, '</a><hr><a target="_new" href = "https://crm.mito.org.nz/MITOCRM/main.aspx?etc=2&extraqs=formid%3d85b5f7f3-ac5a-4beb-95da-2fb3e6b50f38&id=%7b',u.idnumber,'%7d&pagetype=entityrecord">',u.username,'</a><hr>', u.phone1) As 'Learner',
CONCAT(u.department, '<hr>',u.phone2, '<hr>ITA: ', IF ((SELECT 
ITA.email 
FROM prefix_user AS u1
JOIN prefix_context AS u1context ON u1context.instanceid = u1.id
JOIN prefix_role_assignments AS u1ra ON u1ra.contextid = u1context.id
LEFT JOIN prefix_user AS ITA ON u1ra.userid = ITA.id 
WHERE 
u1.id = u.id
AND ITA.email LIKE '%@mito.org.nz' 
) IS NULL, 'Not assigned', (SELECT 
CONCAT(ITA.firstname, ' ', ITA.lastname) 
FROM prefix_user AS u1
JOIN prefix_context AS u1context ON u1context.instanceid = u1.id
JOIN prefix_role_assignments AS u1ra ON u1ra.contextid = u1context.id
LEFT JOIN prefix_user AS ITA ON u1ra.userid = ITA.id 
WHERE 
u1.id = u.id
AND ITA.email LIKE '%@mito.org.nz' 
))) As Employer,


>>>>>>> 52bcfed024a9796617e2edc8474d5132bc1923da:Optimised/National Assessor Dashboard/National Assessor - First Attempt Queue.sql
CONCAT(prog.fullname,'<hr><a target="_new" href = "https://elearning.mito.org.nz/course/view.php?id=', CAST(c.id AS CHAR), '">',CAST(c.fullname AS CHAR),'</a><hr><a target="_new" href = "https://elearning.mito.org.nz/mod/quiz/view.php?id=', CAST(cm.id AS CHAR), '">',CAST(quiz.name AS CHAR),'</a>') AS 'Programme and course', 
CONCAT('Atmpt: ', attempts.attempt,'<hr><a target="_new" href = "https://elearning.mito.org.nz/mod/quiz/review.php?attempt=', CAST(attempts.id AS CHAR), '">Grade attempt</a>') AS 'Grade',
CONCAT('<a target="_new" href = "https://elearning.mito.org.nz/blocks/completionstatus/details.php?course=', CAST(c.id AS CHAR), '&user=',CAST(u.id AS CHAR),'">Check course progress</a><hr><a href="mailto:', u.email, '?cc=elearning@mito.org.nz&Subject=MITO eLearning - ', c.fullname, ' – ', quiz.name, '&body=Kia ora ',u.firstname, '%0D%0A%0D%0AI have marked your written assessment for the course ',c.fullname,'.%0D%0A%0D%0ACongratulations! You have passed the written assessment.%0D%0A%0D%0AHere\'s a link to the marked attempt: https://elearning.mito.org.nz/mod/quiz/review.php?attempt=',CAST(attempts.id AS CHAR),'%0D%0A%0D%0AYou have now completed all of the assessments for this course.%0D%0A%0D%0APlease note you still have topic assessments for this course that you need to complete.','">Send completed email (Outlook)</a>','<hr><a target="_new" href="https://mail.google.com/mail/?view=cm&fs=1&tf=1&to=',u.email, '&cc=elearning@mito.org.nz&su=MITO eLearning - ', c.fullname, ' – ', quiz.name, '&body=Kia ora ',u.firstname, '%0D%0A%0D%0AI have marked your written assessment for the course ',c.fullname,'.%0D%0A%0D%0ACongratulations! You have passed the written assessment.%0D%0A%0D%0AHere\'s a link to the marked attempt: https://elearning.mito.org.nz/mod/quiz/review.php?attempt=',CAST(attempts.id AS CHAR),'%0D%0A%0D%0AYou have now completed all of the assessments for this course.%0D%0A%0D%0APlease note you still have topic assessments for this course that you need to complete.','">Send completed email (Gmail)</a>') AS 'Passed Actions', 
CONCAT('<a target="_new" href = "https://elearning.mito.org.nz/mod/quiz/overrides.php?cmid=', CAST(cm.id AS CHAR), '&mode=user">Add override</a><hr><a href="mailto:', u.email, '?cc=elearning@mito.org.nz&Subject=MITO eLearning - ', c.fullname, ' – ', quiz.name, '&body=Kia ora ',u.firstname, '%0D%0A%0D%0AI have marked your written assessment for the course ',c.fullname,'.%0D%0A%0D%0AUnfortunately you have not provided sufficient answers for all of the questions.%0D%0A%0D%0AI have added comments to the assessment where you need to provide additional information.%0D%0A%0D%0AHere\'s a link to the marked attempt: https://elearning.mito.org.nz/mod/quiz/review.php?attempt=',CAST(attempts.id AS CHAR),'%0D%0A%0D%0AYou will need to start a new attempt and re-do any questions that you did not pass on your earlier attempt (you do not need to add anything to questions that were marked correct).%0D%0A%0D%0APlease reply to this email if you have any questions.','">Send failed email (Outlook)</a>','<hr><a target="_new" href="https://mail.google.com/mail/?view=cm&fs=1&tf=1&to=',u.email, '&cc=elearning@mito.org.nz&su=MITO eLearning - ', c.fullname, ' – ', quiz.name, '&body=Kia ora ',u.firstname, '%0D%0A%0D%0AI have marked your written assessment for the course ',c.fullname,'.%0D%0A%0D%0AUnfortunately you have not provided sufficient answers for all of the questions.%0D%0A%0D%0AI have added comments to the assessment where you need to provide additional information.%0D%0A%0D%0AHere\'s a link to the marked attempt: https://elearning.mito.org.nz/mod/quiz/review.php?attempt=',CAST(attempts.id AS CHAR),'%0D%0A%0D%0AYou will need to start a new attempt and re-do any questions that you did not pass on your earlier attempt (you do not need to add anything to questions that were marked correct).%0D%0A%0D%0APlease reply to this email if you have any questions.','">Send failed email (Gmail)</a>') AS 'Failed Actions'

FROM prefix_quiz_attempts attempts
JOIN prefix_quiz quiz ON attempts.quiz=quiz.id
JOIN prefix_course_modules cm ON cm.instance=quiz.id and cm.module = 18
JOIN prefix_user u ON u.id = attempts.userid
LEFT JOIN prefix_quiz_overrides qover ON qover.quiz = quiz.id AND qover.userid = u.id
JOIN prefix_course c ON c.id = cm.course
JOIN prefix_course_categories AS cc ON cc.id = c.category 
JOIN prefix_grade_items gi ON gi.iteminstance = cm.instance AND gi.courseid = c.id
JOIN prefix_grade_grades gg ON gg.itemid = gi.id AND gg.userid = u.id
LEFT JOIN prefix_prog_user_assignment AS progua ON progua.userid = u.id 
LEFT JOIN prefix_prog AS prog ON prog.id = progua.programid
LEFT Join prefix_user_info_data uid ON uid.userid = u.id
LEFT JOIN prefix_user_info_field uif ON uid.fieldid = uif.id
LEFT JOIN prefix_course_modules_completion cmc ON cmc.userid = u.id AND cmc.coursemoduleid = cm.id

<<<<<<< HEAD:Optimised/01-AE-ALLGROUPS-WRITTENTOMARK-FIRSTATTEMPT-FILTER.sql
FROM prefix_quiz_attempts attempts
JOIN prefix_quiz quiz ON attempts.quiz=quiz.id
JOIN prefix_course_modules cm ON cm.instance=quiz.id and cm.module = 18
JOIN prefix_user u ON u.id = attempts.userid
LEFT JOIN prefix_quiz_overrides qover 
	ON qover.quiz = quiz.id AND qover.userid = u.id
JOIN prefix_course c 
	ON c.id = cm.course
JOIN prefix_course_categories AS cc 
	ON cc.id = c.category 
JOIN prefix_grade_items gi 
	ON gi.iteminstance = cm.instance 
		AND gi.courseid = c.id
JOIN prefix_grade_grades gg ON gg.itemid = gi.id AND gg.userid = u.id
LEFT JOIN prefix_prog_user_assignment AS progua ON progua.userid = u.id 
LEFT JOIN prefix_prog AS prog ON prog.id = progua.programid
LEFT Join prefix_user_info_data uid ON uid.userid = u.id
LEFT JOIN prefix_user_info_field uif ON uid.fieldid = uif.id
LEFT JOIN prefix_course_modules_completion cmc ON cmc.userid = u.id AND cmc.coursemoduleid = cm.id 
INNER JOIN prefix_prog_courseset pcs ON pcs.programid=prog.id 
INNER JOIN prefix_prog_courseset_course pcsc ON pcsc.coursesetid = pcs.id AND pcsc.courseid = c.id

/*INNER JOIN prefix_user_info_data cuid on cuid.userid = cu.id and uid.data = cuid.data
*/
WHERE 
quiz.preferredbehaviour = 'deferredfeedback'
AND (UNIX_TIMESTAMP() - attempts.timefinish)/86400 <= 7
AND attempts.timefinish != 0
AND (gg.finalgrade < 10 or gg.finalgrade IS NULL)
AND (cmc.completionstate IS NULL OR (cmc.completionstate!= 1 AND cmc.completionstate != 2))
AND (attempts.attempt >= 1 AND qover.attempts IS NULL)
AND cc.name = 'NZ Cert Auto Engineering'
AND c.category NOT LIKE 'CRS L5 2021'
AND c.category NOT LIKE 'Business and management'
AND prog.fullname NOT LIKE 'Commercial Road Transport – Heavy Combination Vehicle Loading Fundamentals Micro-credential 2021%'

/*Demo learenrs and test users are hidden from this queue*/
AND u.username != 'elearning.admin' AND u.username != 'mito2' AND u.username != 'mito1' AND u.username != 'mito_nzcr' AND u.username != 'mito_nzar' AND u.username != 'mito1' AND u.username != 'mito_nzlv' AND u.username != 'mito_suig'
AND u.username != '773294@mymitonz.org.nz' AND u.username != '773288@mymitonz.org.nz' AND u.username != '773290@mymitonz.org.nz' AND u.username != 'demolearner'
AND u.username != 'keri.youngman@mymitonz.org.nz'
AND u.username != '544860'AND u.username != '526557'
/**/

=======
WHERE 
quiz.preferredbehaviour = 'deferredfeedback'
AND attempts.timefinish != 0
AND (gg.finalgrade < 10 or gg.finalgrade IS NULL)
AND (attempts.attempt >= 1 AND qover.attempts IS NULL)
AND (cmc.completionstate IS NULL OR (cmc.completionstate!= 1 AND cmc.completionstate != 2))
/*Admin users, Demo learenrs and test users are hidden from this queue*/
AND u.username != 'elearning.admin'  AND u.username != 'mito2' AND u.username != 'mito_nzcr' AND u.username != 'mito_nzar' AND u.username != 'kburke' AND u.username != 'iclarke' AND u.username != 'evdemo' AND u.username != 'mito_suig' AND u.username != 'mito1'
AND u.username != '773294@mymitonz.org.nz' AND u.username != '773288@mymitonz.org.nz' AND u.username != '773290@mymitonz.org.nz' AND u.username != 'demolearner'
AND u.username != 'gbalasuriya' AND u.username != 'hclark' AND u.username != 'kahmad' 
/**/
>>>>>>> 52bcfed024a9796617e2edc8474d5132bc1923da:Optimised/National Assessor Dashboard/National Assessor - First Attempt Queue.sql
AND prog.fullname IS NOT NULL
AND u.suspended = 0
AND uif.shortname = 'assessorgroup'
AND uid.data = 'National assessor'
AND cc.name != 'COF'
<<<<<<< HEAD:Optimised/01-AE-ALLGROUPS-WRITTENTOMARK-FIRSTATTEMPT-FILTER.sql
AND c.shortname != 'GEN-SS135-ELv1'
AND c.shortname NOT LIKE 'CRAR-US5744-ELv1'
AND c.shortname NOT LIKE 'EL-AB-3RCv1'
AND c.shortname NOT LIKE 'EL-CP-3RCv1'
AND 1=1

/*AND c.shortname NOT LIKE 'FLM%'
AND c.shortname NOT LIKE 'CRS-SS182-ELv1'
AND c.shortname NOT LIKE 'CRS-SS183-ELv1'
AND c.shortname NOT LIKE 'CRS-SS184-ELv1'
AND c.shortname NOT LIKE 'CRS-SS186-PAv1'
AND c.shortname NOT LIKE 'CRS-SS187-ELv1'
*/

%%FILTER_USERS:uid.data%%
=======

ORDER BY attempts.timefinish ASC, u.firstname, c.fullname, quiz.name

>>>>>>> 52bcfed024a9796617e2edc8474d5132bc1923da:Optimised/National Assessor Dashboard/National Assessor - First Attempt Queue.sql

