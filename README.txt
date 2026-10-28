CSTA HR

This is a small standalone PHP application. It does not use a database.
HR account and faculty records are stored in PHP data files in the storage
folder. Passwords and the recovery code are stored as password hashes. The
CSTA seal image is included as csta-logo.png and appears on the sign-in and
first-time account setup pages. The interface uses colors inspired by the seal.

REQUIREMENTS
- PHP 8.1 or later

RUN LOCALLY
EASY START (WINDOWS)
1. Make sure PHP is installed and available in PATH.
2. Double-click "Run CSTA HR.bat" in this project folder.
3. The app opens in your browser. Leave the command window open while using it.
4. To stop the server, press Ctrl+C in the command window.

SHORT TERMINAL COMMAND
Open the terminal in this project folder and type:
   .\run.bat

MANUAL START
1. Open a terminal in this project folder.
2. Run: php -S 127.0.0.1:8000 -t .
3. Visit http://127.0.0.1:8000/System.php in your browser.

On first visit, create the HR username, password, and recovery code. Keep the
recovery code private and keep a backup of the storage folder.

After signing in, use the left navigation to open each section as its own page:
- Overview: http://127.0.0.1:8000/dashboard.php
- Add Credentials: http://127.0.0.1:8000/add-faculty.php
- Faculty Ranking: http://127.0.0.1:8000/faculty-ranking.php
- Scoring Rubric: http://127.0.0.1:8000/scoring-rubric.php

The Faculty Ranking page can search and filter records, edit credentials,
download the ranking as CSV, and print the currently filtered report.
The Overview page also includes score, degree, and credential-point charts.
Open a faculty profile by clicking their name in the ranking; long rankings
are split into pages of 10 records.

HR can edit scoring values, section caps, rank point ranges, and hourly rates
on the Scoring Rubric page. Settings are saved in
storage/ranking-policy.php, and faculty ranks are recalculated using the
current policy. The rank ranges must remain continuous without gaps or
overlaps. Use "Restore original policy" to return to the starting values.

If port 8000 is already in use, stop the other server first, or start manually
on port 8080 with: php -S 127.0.0.1:8080 -t .

The app creates storage/hr-account.php and storage/faculty.php on first use.
Do not delete these files unless you intend to reset the account or records.
For a public server, use HTTPS and configure the web server to deny direct
access to the storage folder. The included .htaccess does this on Apache.

SCORING AND COMPATIBILITY
The Scoring Rubric page contains the 500-point matrix, rank prerequisites, and
hourly rate bands transcribed from the provided faculty ranking forms.
Implementation decisions: a 4-year bachelor's degree is 20 points as
confirmed; competence uses the listed raw points with a 100-point section cap;
rank requirements are enforced cumulatively; hourly rates are shown in PHP.
The visible Section II rules total at most 120 points although the form lists
150. No missing points are invented, so the current visible rules produce at
most 470 out of 500. The form does not give a numeric value for patented
works, so those are not scored.
The HR editor can change the point values and score bands when an approved
policy update is available. The system does not create an audit history of
policy edits; HR should document approved changes externally and back up the
storage folder.

Older faculty records made under the former sample rubric are preserved, but
marked "Requires rescoring" because their fields do not contain the new
instrument's evidence. Edit those records in Faculty Ranking and enter their
credentials under the current form before assigning a rank or rate.

RECOVERY
Use "Forgot username or password?", enter the HR username and recovery code
set during first-time setup, then choose a new password.
If both the password and recovery code are lost, restore the HR account from
a backup or remove storage/hr-account.php to run first-time setup again.
Removing the account file does not delete faculty records.
