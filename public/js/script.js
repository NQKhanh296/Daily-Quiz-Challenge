let currentQuestion = 0;
let score = 0;
let timer = 0;
let timerInterval;

document.addEventListener("DOMContentLoaded", function () {
  loadTodayQuiz();
});

async function loadTodayQuiz() {
  try {
    const response = await fetch("/api/quiz/today");
    const data = await response.json();
    const topicElement = document.querySelector("#todayTopic span");
    if (topicElement) topicElement.textContent = data.topic || "Neznámé téma";
  } catch (error) {
    console.error(error);
  }
}

async function startQuiz(selectedDifficulty) {
  try {
    const response = await fetch("/api/quiz/start", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ difficulty: selectedDifficulty }),
    });

    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.error || "Server odmítl start");
    }

    const data = await response.json();
    currentQuestion = 0;
    score = 0;
    document.getElementById("score").textContent = "0";

    showScreen("quizScreen");
    startTimer();
    renderQuestion(data.question);
  } catch (error) {
    alert(error.message);
  }
}

function renderQuestion(questionData) {
  currentQuestion++;
  document.getElementById("questionNum").textContent = currentQuestion;
  document.getElementById("questionText").textContent = questionData.text;

  document.getElementById("nextBtn").classList.add("hidden");

  const answersDiv = document.getElementById("answers");
  answersDiv.innerHTML = "";

  questionData.options.forEach((option, index) => {
    const btn = document.createElement("button");
    btn.textContent = option;
    btn.className = "answer-btn";
    btn.onclick = () => submitAnswer(index);
    answersDiv.appendChild(btn);
  });
}

async function submitAnswer(answerIndex) {
  try {
    const response = await fetch("/api/quiz/submit-answer", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ answer_index: answerIndex }),
    });

    const data = await response.json();
    const buttons = document.querySelectorAll(".answer-btn");
    buttons.forEach((btn) => (btn.disabled = true));

    if (data.correct) {
      buttons[answerIndex].classList.add("correct");
    } else {
      buttons[answerIndex].classList.add("wrong");
      if (data.correct_index !== undefined && buttons[data.correct_index]) {
        buttons[data.correct_index].classList.add("correct");
      }
    }

    if (data.earned_points) {
      score += data.earned_points;
      document.getElementById("score").textContent = score;
    }

    const nextBtn = document.getElementById("nextBtn");
    nextBtn.textContent =
      currentQuestion < 3 ? "Další otázka" : "Dokončit kvíz";
    nextBtn.classList.remove("hidden");
  } catch (error) {
    alert("Chyba: " + error.message);
  }
}

function handleNextStep() {
  if (currentQuestion < 3) {
    loadQuestion();
  } else {
    fetchFinalResults();
  }
}

async function loadQuestion() {
  try {
    const response = await fetch("/api/quiz/fetch-question");
    const data = await response.json();

    if (data.status === "finished") {
      finishQuiz(data.total_points);
      return;
    }
    renderQuestion(data);
  } catch (error) {
    finishQuiz();
  }
}

async function fetchFinalResults() {
  try {
    const response = await fetch("/api/quiz/fetch-question");
    const data = await response.json();
    if (data.status === "finished") {
      finishQuiz(data.total_points);
    }
  } catch (e) {
    finishQuiz();
  }
}

function finishQuiz(totalPoints) {
  stopTimer();
  document.getElementById("finalScore").textContent = totalPoints || score;
  document.getElementById("finalTime").textContent = timer;
  showScreen("resultScreen");
}

function startTimer() {
  timer = 0;
  if (timerInterval) clearInterval(timerInterval);
  timerInterval = setInterval(() => {
    timer++;
    const el = document.getElementById("timer");
    if (el) el.textContent = timer;
  }, 1000);
}

function stopTimer() {
  clearInterval(timerInterval);
}

function showScreen(screenId) {
  document
    .querySelectorAll(".screen")
    .forEach((screen) => screen.classList.add("hidden"));
  const el = document.getElementById(screenId);
  if (el) el.classList.remove("hidden");
}
