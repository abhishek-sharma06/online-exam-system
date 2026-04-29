# online-exam-system
monitors candidates by recording violations and disqualifies if done

# Exam System — Simple Guide

This is a small website to give and take online exams. It is written in PHP and made to run on a local server like XAMPP. This guide uses very easy words so anyone can understand — even a 5th grader.

## What is it?
- A place where teachers (admins) make tests.
- Students (candidates) can sign up, take tests, and see results.
- It keeps pictures and logs to check if someone tried to cheat.

## Who uses it?
- Admin: the teacher or person who makes the exam and checks results.
- Candidate: the student who takes the exam.

## How to open and run it (easy steps)
1. Make sure you have XAMPP installed and running (Apache and MySQL on).
2. Put the project folder `exam_system` into XAMPP's `htdocs` folder.
3. Start Apache and MySQL from the XAMPP control panel.
4. Open your web browser and go to: `http://localhost/exam_system`.
5. Set up the database by opening `setup_database.php` in your browser and following the instructions.
6. Create an admin account by opening `setup_admin.php` and entering details.
7. Admin can log in from `admin/dashboard.php` to make exams and view reports.
8. Students can register using `register.php` and take exams from the `candidate` pages.

## Where files are (short)
- admin/ — pages for the person who manages the exams.
- candidate/ — pages for students to take exams and see results.
- config/ — database settings.
- includes/ — helpers used by many pages (code pieces).
- assets/ — styles (CSS), JavaScript, and saved images.

## How to use (for a teacher/admin)
1. Log in to the admin area.
2. Create an exam and add questions.
3. Tell students how to sign up and when the test will start.
4. After the test, check results and any rule-break logs.

## How to use (for a student)
1. Open `http://localhost/exam_system` in your browser.
2. Click Register and make your account.
3. Log in and go to the test page to start the exam.
4. Finish all questions and submit your exam.
5. You can see your score in the results page.

## Safety and rules
- Do not try to cheat. The system records suspicious actions.
- Only the admin should change the database directly.

## Troubleshooting (if something goes wrong)
- If pages show errors, make sure Apache and MySQL are running.
- Make sure the project folder is in `htdocs`.
- If the database is missing, re-run `setup_database.php`.

## Want help?
If you want, I can add pictures, short videos, or make the steps even simpler. Tell me what you want next.

---
_File created for simple learning and quick setup._
