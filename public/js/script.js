const questions = [
    { q: "V jakém roce vznikla ČR?", a: ["1990", "1993", "1989", "2000"], correct: 1 },
    { q: "Která planeta je nejblíže Slunci?", a: ["Venuše", "Mars", "Merkur", "Země"], correct: 2 },
    { q: "Jak se jmenuje zakladatel Symfony?", a: ["Fabien Potencier", "Steve Jobs", "Mark Zuckerberg", "Jan Novák"], correct: 0 }
];

let currentIdx = 0;
let score = 0;
let timer = 0;
let timerInterval;

function startQuiz(difficulty) {
    document.getElementById('start-screen').classList.add('hidden');
    document.getElementById('quiz-screen').classList.remove('hidden');

    timerInterval = setInterval(() => {
        timer++;
        document.getElementById('timer').innerText = `Čas: ${timer}s`;
    }, 1000);

    showQuestion();
}

function showQuestion() {
    const q = questions[currentIdx];
    document.getElementById('question-text').innerText = q.q;
    document.getElementById('question-number').innerText = `Otázka ${currentIdx + 1}/${questions.length}`;

    const container = document.getElementById('answer-buttons');
    container.innerHTML = '';

    q.a.forEach((ans, i) => {
        const btn = document.createElement('button');
        btn.innerText = ans;
        btn.onclick = () => checkAnswer(i);
        container.appendChild(btn);
    });

    document.getElementById('progress-fill').style.width = `${(currentIdx / questions.length) * 100}%`;
}

function checkAnswer(selected) {
    if (selected === questions[currentIdx].correct) score += 10;

    currentIdx++;
    if (currentIdx < questions.length) {
        showQuestion();
    } else {
        finishQuiz();
    }
}

function finishQuiz() {
    clearInterval(timerInterval);
    document.getElementById('quiz-screen').classList.add('hidden');
    document.getElementById('result-screen').classList.remove('hidden');

    document.getElementById('final-score').innerText = score;
    document.getElementById('final-time').innerText = timer;
    document.getElementById('speed-bonus').innerText = timer < 20 ? 20 : 0;
}