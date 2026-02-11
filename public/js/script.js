let currentQuestion = 0;
let score = 0;
let timer = 0;
let timerInterval;

document.addEventListener('DOMContentLoaded', function() {
    loadTodayQuiz();
});

async function loadTodayQuiz() {
    try {
        console.log('Načítám info o kvízu');
        const response = await fetch('/api/quiz/today');

        if (!response.ok) {
            throw new Error(`Chyba serveru: ${response.status} ${response.statusText}`);
        }

        const data = await response.json();
        console.log('Data z /api/quiz/today:', data);

        const topicElement = document.querySelector('#todayTopic span');
        if (topicElement) {
            topicElement.textContent = data.topic || 'Neznámé téma';
        }

    } catch (error) {
        console.error('Chyba loadTodayQuiz:', error);
        document.querySelector('#todayTopic span').textContent = 'Nepodařilo se načíst';
    }
}

async function startQuiz(selectedDifficulty) {
    try {
        console.log('Spouštím kvíz s obtížností:', selectedDifficulty);

        const response = await fetch('/api/quiz/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ difficulty: selectedDifficulty })
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Server odmítl start: ${errorText} (Kód: ${response.status})`);
        }

        showScreen('quizScreen');
        startTimer();
        loadQuestion();

    } catch (error) {
        console.error('Chyba startQuiz:', error);
        alert('Nepodařilo se spustit kvíz:\n' + error.message);
    }
}

async function loadQuestion() {
    try {
        const response = await fetch('/api/quiz/fetch-question');

        if (!response.ok) {
            throw new Error('Nepodařilo se stáhnout otázku');
        }

        const data = await response.json();
        console.log('Otázka:', data);

        currentQuestion++;
        document.getElementById('questionNum').textContent = currentQuestion;
        document.getElementById('questionText').textContent = data.text;

        const answersDiv = document.getElementById('answers');
        answersDiv.innerHTML = '';

        data.options.forEach((option, index) => {
            const btn = document.createElement('button');
            btn.textContent = option;
            btn.className = 'answer-btn';
            btn.onclick = () => submitAnswer(index);
            answersDiv.appendChild(btn);
        });

    } catch (error) {
        console.error(error);
        alert('Konec kvízu nebo chyba: ' + error.message);
        finishQuiz();
    }
}

async function submitAnswer(answerIndex) {
    try {
        const response = await fetch('/api/quiz/submit-answer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ answer_index: answerIndex })
        });

        const data = await response.json();
        console.log('Výsledek odpovědi:', data);

        const buttons = document.querySelectorAll('.answer-btn');
        buttons.forEach(btn => btn.disabled = true);

        if (data.correct) {
            buttons[answerIndex].classList.add('correct');
        } else {
            buttons[answerIndex].classList.add('wrong');
            if (data.correct_index !== undefined && buttons[data.correct_index]) {
                buttons[data.correct_index].classList.add('correct');
            }
        }

        if (data.points) {
            score += data.points;
        }
        document.getElementById('score').textContent = score;

        setTimeout(() => {
            if (currentQuestion < 3) {
                loadQuestion();
            } else {
                finishQuiz();
            }
        }, 1500);

    } catch (error) {
        alert('Chyba při odesílání: ' + error.message);
    }
}

function finishQuiz() {
    stopTimer();
    document.getElementById('finalScore').textContent = score;
    document.getElementById('finalTime').textContent = timer;
    showScreen('resultScreen');
}

function startTimer() {
    timer = 0;
    timerInterval = setInterval(() => {
        timer++;
        const el = document.getElementById('timer');
        if(el) el.textContent = timer;
    }, 1000);
}

function stopTimer() {
    clearInterval(timerInterval);
}

function showScreen(screenId) {
    document.querySelectorAll('.screen').forEach(screen => {
        screen.classList.add('hidden');
    });
    const el = document.getElementById(screenId);
    if(el) el.classList.remove('hidden');
}

function showLeaderboard() {
    showScreen('leaderboardScreen');
}

function switchTab(type) {
    console.log("Switch tab:", type);
}