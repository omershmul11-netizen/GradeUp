document.body.classList.add('gradeup-page');

const startBtn = document.getElementById('startBtn');
const gradeSelect = document.getElementById('grade');
const subjectSelect = document.getElementById('subject');
const loadingEl = document.getElementById('loading');
const messageEl = document.getElementById('message');
const resultsSection = document.getElementById('resultsSection');
const studentsContainer = document.getElementById('studentsContainer');

function showMessage(text, type = 'error') {
    messageEl.textContent = text;
    messageEl.className = `message ${type}`;
    messageEl.classList.remove('hidden');
}

function hideMessage() {
    messageEl.textContent = '';
    messageEl.className = 'message hidden';
}

function showLoading() {
    loadingEl.classList.remove('hidden');
}

function hideLoading() {
    loadingEl.classList.add('hidden');
}

function clearResults() {
    studentsContainer.innerHTML = '';
    resultsSection.classList.add('hidden');
}

function renderStudents(students) {
    studentsContainer.innerHTML = '';

    students.forEach(student => {
        const card = document.createElement('div');
        card.className = 'student-card';

        card.innerHTML = `
            <div class="student-name">${student.first_name} ${student.last_name}</div>
            <div class="student-details">
                <div><strong>כיתה:</strong> ${student.class_name}</div>
                <div><strong>שכבה:</strong> ${student.grade_level}</div>
                <div><strong>מקצוע:</strong> ${student.subject_name}</div>
                <div><strong>ציון אחרון:</strong> ${student.latest_grade}</div>
                <div><strong>אימייל:</strong> ${student.email}</div>
            </div>
        `;

        studentsContainer.appendChild(card);
    });

    resultsSection.classList.remove('hidden');
}

startBtn.addEventListener('click', async () => {
    const grade = gradeSelect.value;
    const subject = subjectSelect.value;

    hideMessage();
    clearResults();

    if (!grade || !subject) {
        showMessage('יש לבחור שכבה ומקצוע לפני התחלת השיבוץ');
        return;
    }

    showLoading();

    try {
        const url = `api/get_students_for_matching.php?grade=${encodeURIComponent(grade)}&subject=${encodeURIComponent(subject)}`;
        const response = await fetch(url);
        const data = await response.json();

        hideLoading();

        if (!data.success) {
            showMessage(data.message || 'אירעה שגיאה בקבלת הנתונים');
            return;
        }

        if (data.count === 0) {
            showMessage('לא נמצאו תלמידים מתאימים', 'success');
            return;
        }

        showMessage(`נמצאו ${data.count} תלמידים מתאימים`, 'success');
        renderStudents(data.students);

    } catch (error) {
        hideLoading();
        showMessage('שגיאת תקשורת עם השרת');
        console.error(error);
    }
});