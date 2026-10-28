<label class="wide-field">Faculty name
    <input name="name" required maxlength="100" value="<?= h((string) ($formRecord['name'] ?? '')) ?>" placeholder="Enter full name">
</label>

<fieldset class="credential-group">
    <legend>I. Academic preparation <span>Maximum <?= $rankingPolicy['academic']['cap'] ?> points</span></legend>
    <label>Highest degree obtained
        <select name="highest_degree" required>
            <?php if (!empty($formRecord['legacy_record'])): ?>
                <option value="" selected disabled>Choose verified degree</option>
            <?php endif; ?>
            <?php foreach ($rankingPolicy['academic']['degree_points'] as $degreeKey => $points): ?>
                <option value="<?= h($degreeKey) ?>" <?= empty($formRecord['legacy_record']) && ($formRecord['highest_degree'] ?? '') === $degreeKey ? 'selected' : '' ?>><?= h(degree_labels()[$degreeKey]) ?> (<?= $points ?> pts)</option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Additional aligned bachelor's degrees
        <input type="number" name="additional_bachelors" min="0" max="50" value="<?= (int) ($formRecord['additional_bachelors'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['academic']['additional_bachelors'] ?> points each; shared cap <?= $rankingPolicy['academic']['additional_cap'] ?></span>
    </label>
    <label>Additional aligned master's degrees
        <input type="number" name="additional_masters" min="0" max="50" value="<?= (int) ($formRecord['additional_masters'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['academic']['additional_masters'] ?> points each; shared cap <?= $rankingPolicy['academic']['additional_cap'] ?></span>
    </label>
    <label>Additional aligned doctor's degrees
        <input type="number" name="additional_doctorates" min="0" max="50" value="<?= (int) ($formRecord['additional_doctorates'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['academic']['additional_doctorates'] ?> points each; shared cap <?= $rankingPolicy['academic']['additional_cap'] ?></span>
    </label>
    <label>Short-course certificates
        <input type="number" name="short_course_certificates" min="0" max="100" value="<?= (int) ($formRecord['short_course_certificates'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['academic']['short_course_points'] ?> points each; maximum <?= $rankingPolicy['academic']['short_course_cap'] ?></span>
    </label>
    <label>Academic honors
        <select name="academic_honors" required>
            <option value="none" <?= ($formRecord['academic_honors'] ?? '') === 'none' ? 'selected' : '' ?>>None (<?= $rankingPolicy['academic']['honors']['none'] ?> points)</option>
            <option value="cum-laude" <?= ($formRecord['academic_honors'] ?? '') === 'cum-laude' ? 'selected' : '' ?>>Cum Laude (<?= $rankingPolicy['academic']['honors']['cum-laude'] ?> points)</option>
            <option value="magna-cum-laude" <?= ($formRecord['academic_honors'] ?? '') === 'magna-cum-laude' ? 'selected' : '' ?>>Magna Cum Laude (<?= $rankingPolicy['academic']['honors']['magna-cum-laude'] ?> points)</option>
            <option value="summa-cum-laude" <?= ($formRecord['academic_honors'] ?? '') === 'summa-cum-laude' ? 'selected' : '' ?>>Summa Cum Laude (<?= $rankingPolicy['academic']['honors']['summa-cum-laude'] ?> points)</option>
        </select>
    </label>
    <label>Government examination passed
        <select name="government_exam" required>
            <option value="none" <?= ($formRecord['government_exam'] ?? '') === 'none' ? 'selected' : '' ?>>None (0 points)</option>
            <option value="subprofessional" <?= ($formRecord['government_exam'] ?? '') === 'subprofessional' ? 'selected' : '' ?>>Civil Service Sub-professional (<?= $rankingPolicy['academic']['exam_subprofessional'] ?> points)</option>
            <option value="professional" <?= ($formRecord['government_exam'] ?? '') === 'professional' ? 'selected' : '' ?>>BAR / Licensure / Civil Service Professional (<?= $rankingPolicy['academic']['exam_professional'] ?> points)</option>
        </select>
    </label>
</fieldset>

<fieldset class="credential-group">
    <legend>Rank prerequisites <span>Required evidence for advancement</span></legend>
    <label>Completed master's degree units
        <input type="number" name="masters_units" min="0" max="500" value="<?= (int) ($formRecord['masters_units'] ?? 0) ?>" required>
        <span class="label-hint">Instructor 2 requires at least 21 units</span>
    </label>
    <label class="check-field">
        <input type="hidden" name="thesis_enrolled" value="0">
        <input type="checkbox" name="thesis_enrolled" value="1" <?= !empty($formRecord['thesis_enrolled']) ? 'checked' : '' ?>>
        Enrolled in thesis / capstone
    </label>
    <label class="check-field">
        <input type="hidden" name="dissertation_enrolled" value="0">
        <input type="checkbox" name="dissertation_enrolled" value="1" <?= !empty($formRecord['dissertation_enrolled']) ? 'checked' : '' ?>>
        Enrolled in dissertation
    </label>
</fieldset>

<fieldset class="credential-group">
    <legend>II. Professional and academic experience <span>Maximum <?= $rankingPolicy['experience']['cap'] ?> points</span></legend>
    <label>Teaching years in CSTA
        <input type="number" name="teaching_years_csta" min="0" max="100" value="<?= (int) ($formRecord['teaching_years_csta'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['experience']['teaching_csta_per_year'] ?> point(s) per year; subtotal cap <?= $rankingPolicy['experience']['teaching_cap'] ?></span>
    </label>
    <label>Teaching years in other HEIs
        <input type="number" name="teaching_years_other" min="0" max="200" value="<?= (int) ($formRecord['teaching_years_other'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['experience']['teaching_other_points_per_period'] ?> point(s) per <?= $rankingPolicy['experience']['teaching_other_years_per_point'] ?> years; shared teaching cap <?= $rankingPolicy['experience']['teaching_cap'] ?></span>
    </label>
    <label>Administrative / consultancy years in CSTA
        <input type="number" name="admin_years_csta" min="0" max="100" value="<?= (int) ($formRecord['admin_years_csta'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['experience']['administrative_csta_per_year'] ?> point(s) per year; subtotal cap <?= $rankingPolicy['experience']['administrative_cap'] ?></span>
    </label>
    <label>Administrative / consultancy years elsewhere
        <input type="number" name="admin_years_other" min="0" max="200" value="<?= (int) ($formRecord['admin_years_other'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['experience']['administrative_other_points_per_period'] ?> point(s) per <?= $rankingPolicy['experience']['administrative_other_years_per_point'] ?> years; shared cap <?= $rankingPolicy['experience']['administrative_cap'] ?></span>
    </label>
    <label>Published books / instructional materials
        <input type="number" name="books_count" min="0" max="100" value="<?= (int) ($formRecord['books_count'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['experience']['book_points'] ?> points each, divided by authors</span>
    </label>
    <label>Authors per book / material
        <input type="number" name="books_authors" min="1" max="100" value="<?= (int) ($formRecord['books_authors'] ?? 1) ?>" required>
    </label>
    <label>Published research / creative works
        <input type="number" name="research_count" min="0" max="100" value="<?= (int) ($formRecord['research_count'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['experience']['research_points'] ?> points each, divided by creators</span>
    </label>
    <label>Creators per research / creative work
        <input type="number" name="research_authors" min="1" max="100" value="<?= (int) ($formRecord['research_authors'] ?? 1) ?>" required>
    </label>
    <label>Implemented project / program proposals
        <input type="number" name="projects_count" min="0" max="100" value="<?= (int) ($formRecord['projects_count'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['experience']['project_points'] ?> points each, divided by co-creators</span>
    </label>
    <label>Co-creators per project
        <input type="number" name="projects_creators" min="1" max="100" value="<?= (int) ($formRecord['projects_creators'] ?? 1) ?>" required>
    </label>
    <label>Training / conference speaker or resource person
        <input type="number" name="conference_speaker" min="0" max="100" value="<?= (int) ($formRecord['conference_speaker'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['experience']['speaker_points'] ?> points each</span>
    </label>
    <label>Training / conference committee chair or reactor
        <input type="number" name="conference_committee" min="0" max="100" value="<?= (int) ($formRecord['conference_committee'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['experience']['committee_points'] ?> points each</span>
    </label>
    <label>Training / conference participant or delegate
        <input type="number" name="conference_participant" min="0" max="100" value="<?= (int) ($formRecord['conference_participant'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['experience']['participant_points'] ?> points each; conference subtotal cap <?= $rankingPolicy['experience']['conference_cap'] ?></span>
    </label>
</fieldset>

<fieldset class="credential-group">
    <legend>III. Competence <span>Maximum <?= $rankingPolicy['competence']['cap'] ?> points; raw listed points are capped</span></legend>
    <label>Dean's evaluation
        <input type="number" name="dean_evaluation" min="0" max="100" value="<?= (int) ($formRecord['dean_evaluation'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['competence']['dean_high_threshold'] ?>–100 = <?= $rankingPolicy['competence']['dean_high_points'] ?> pts; <?= $rankingPolicy['competence']['dean_pass_threshold'] ?>–<?= max($rankingPolicy['competence']['dean_pass_threshold'], $rankingPolicy['competence']['dean_high_threshold'] - 1) ?> = <?= $rankingPolicy['competence']['dean_pass_points'] ?> pts; below <?= $rankingPolicy['competence']['dean_pass_threshold'] ?> = <?= $rankingPolicy['competence']['dean_fail_points'] ?> pts and no promotion</span>
    </label>
    <label>Students' evaluation
        <select name="student_evaluation" required>
            <option value="excellent" <?= ($formRecord['student_evaluation'] ?? '') === 'excellent' ? 'selected' : '' ?>>Excellent (<?= $rankingPolicy['competence']['student_excellent_points'] ?> points)</option>
            <option value="very-satisfactory" <?= ($formRecord['student_evaluation'] ?? '') === 'very-satisfactory' ? 'selected' : '' ?>>Very Satisfactory (<?= $rankingPolicy['competence']['student_very_satisfactory_points'] ?> points)</option>
            <option value="satisfactory" <?= ($formRecord['student_evaluation'] ?? '') === 'satisfactory' ? 'selected' : '' ?>>Satisfactory (<?= $rankingPolicy['competence']['student_satisfactory_points'] ?> points)</option>
        </select>
    </label>
    <label>National awards / recognition
        <input type="number" name="national_awards" min="0" max="100" value="<?= (int) ($formRecord['national_awards'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['competence']['national_award_points'] ?> points each; awards cap <?= $rankingPolicy['competence']['award_cap'] ?></span>
    </label>
    <label>Institutional awards / recognition
        <input type="number" name="institutional_awards" min="0" max="100" value="<?= (int) ($formRecord['institutional_awards'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['competence']['institutional_award_points'] ?> points each; shared cap <?= $rankingPolicy['competence']['award_cap'] ?></span>
    </label>
    <label>International fellowship / grants
        <input type="number" name="international_grants" min="0" max="100" value="<?= (int) ($formRecord['international_grants'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['competence']['international_grant_points'] ?> points each; grants cap <?= $rankingPolicy['competence']['grant_cap'] ?></span>
    </label>
    <label>Local fellowship / grants
        <input type="number" name="local_grants" min="0" max="100" value="<?= (int) ($formRecord['local_grants'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['competence']['local_grant_points'] ?> points each; shared cap <?= $rankingPolicy['competence']['grant_cap'] ?></span>
    </label>
    <label>Professional organization president
        <input type="number" name="affiliation_president" min="0" max="100" value="<?= (int) ($formRecord['affiliation_president'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['competence']['affiliation_president_points'] ?> points each</span>
    </label>
    <label>Other professional organization positions
        <input type="number" name="affiliation_position" min="0" max="100" value="<?= (int) ($formRecord['affiliation_position'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['competence']['affiliation_position_points'] ?> points each</span>
    </label>
    <label>Professional organization memberships
        <input type="number" name="affiliation_member" min="0" max="100" value="<?= (int) ($formRecord['affiliation_member'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['competence']['affiliation_member_points'] ?> points each; affiliation cap <?= $rankingPolicy['competence']['affiliation_cap'] ?></span>
    </label>
</fieldset>

<fieldset class="credential-group">
    <legend>IV. Extension and community service <span>Maximum <?= $rankingPolicy['extension']['cap'] ?> points</span></legend>
    <label>Extension-service participation days
        <input type="number" name="extension_days" min="0" max="100" value="<?= (int) ($formRecord['extension_days'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['extension']['participation_day_points'] ?> points per day; maximum <?= $rankingPolicy['extension']['participation_cap'] ?></span>
    </label>
    <label>Adviser / chair / panel service years or titles
        <input type="number" name="extension_advisor_years" min="0" max="100" value="<?= (int) ($formRecord['extension_advisor_years'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['extension']['advisor_points'] ?> points each; maximum <?= $rankingPolicy['extension']['advisor_cap'] ?></span>
    </label>
    <label>Member / editor / statistician service years or titles
        <input type="number" name="extension_member_years" min="0" max="100" value="<?= (int) ($formRecord['extension_member_years'] ?? 0) ?>" required>
        <span class="label-hint"><?= $rankingPolicy['extension']['member_points'] ?> point(s) each; maximum <?= $rankingPolicy['extension']['member_cap'] ?></span>
    </label>
</fieldset>
