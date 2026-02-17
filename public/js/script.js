let currentWords = [];
let currentQuestion = 0;
let score = 0;
let timer = 0;
let timerInterval;

// Hned po načtení stránky načteme 3 slova pro login
document.addEventListener("DOMContentLoaded", function () {
  generateNewWords();
});

// --- AUTHENTIKACE ---

async function generateNewWords() {
  try {
    const response = await fetch("/api/registration-code");
    const data = await response.json();
    currentWords = data.words;

    const spans = document.querySelectorAll("#wordDisplay .word");
    currentWords.forEach((word, i) => (spans[i].innerText = word));
  } catch (e) {
    console.error("Chyba při načítání slov", e);
  }
}

async function authenticateUser() {
  try {
    const response = await fetch("/api/authenticate", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ words: currentWords }),
    });

    const data = await response.json();

    if (response.ok) {
      // Úspěšně přihlášeno (session cookie nastavena)
      showScreen("startScreen");
      loadTodayQuiz();
    } else {
      alert("Chyba: " + data.error);
    }
  } catch (e) {
    alert("Server neodpovídá.");
  }
}

// --- LOGIKA KVÍZU ---

async function loadTodayQuiz() {
  try {
    const response = await fetch("/api/quiz/today");
    if (response.status === 404) {
      document.querySelector("#todayTopic span").textContent =
        "Dnes není žádný kvíz.";
      return;
    }
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

  // Ošetření, pokud options přijdou z backendu jako string
  let options =
    typeof questionData.options === "string"
      ? JSON.parse(questionData.options)
      : questionData.options;

  options.forEach((option, index) => {
    const btn = document.createElement("button");
    btn.textContent = option;
    btn.className = "answer-btn";
    btn.onclick = () => submitAnswer(index, btn);
    answersDiv.appendChild(btn);
  });
}

async function submitAnswer(answerIndex, clickedBtn) {
  // Okamžitě zablokujeme všechny tlačítka, aby nešlo kliknout víckrát
  const buttons = document.querySelectorAll(".answer-btn");
  buttons.forEach((btn) => (btn.disabled = true));

  try {
    const response = await fetch("/api/quiz/submit-answer", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ answer_index: answerIndex }),
    });

    const data = await response.json();

    // Obarvení správné/špatné
    if (data.correct) {
      clickedBtn.classList.add("correct");
    } else {
      clickedBtn.classList.add("wrong");
      // Ukážeme správnou odpověď
      if (data.correct_index !== undefined && buttons[data.correct_index]) {
        buttons[data.correct_index].classList.add("correct");
      }
    }

    // Přičtení bodů (čistá logika bez DOM innerText, DOM updatujeme na konci)
    if (data.earned_points) {
      score += data.earned_points;
      document.getElementById("score").textContent = score;
    }

    const nextBtn = document.getElementById("nextBtn");
    nextBtn.textContent =
      currentQuestion < 3 ? "Další otázka" : "Dokončit kvíz";
    nextBtn.classList.remove("hidden");
  } catch (error) {
    alert("Chyba při odesílání odpovědi.");
  }
}

function handleNextStep() {
  if (currentQuestion < 3) {
    loadQuestion();
  } else {
    // Pokud jsme u otázky 3 a klikneme "Dokončit kvíz", musíme zavolat API pro dokončení
    finishQuizRequest();
  }
}

async function loadQuestion() {
  try {
    const response = await fetch("/api/quiz/fetch-question");
    const data = await response.json();

    if (data.status === "finished") {
      showFinalResults(data.total_points);
      return;
    }
    renderQuestion(data);
  } catch (error) {
    alert("Chyba při načítání další otázky.");
  }
}

async function finishQuizRequest() {
  try {
    // Zavoláme fetch-question i když víme, že jsme na konci. Backend pozná, že step >= 3 a pokus uzavře.
    const response = await fetch("/api/quiz/fetch-question");
    const data = await response.json();

    if (data.status === "finished") {
      showFinalResults(data.total_points);
    }
  } catch (e) {
    showFinalResults(score); // Fallback
  }
}

function showFinalResults(totalPoints) {
  stopTimer();
  document.getElementById("finalScore").textContent = totalPoints || score;
  document.getElementById("finalTime").textContent = timer;
  showScreen("resultScreen");
}

function showLeaderboard() {
  showScreen("leaderboardScreen");
  // Tady pak můžeš přidat fetch pro /api/leaderboard
}

// --- POMOCNÉ FUNKCE ---

function startTimer() {
  timer = 0;
  document.getElementById("timer").textContent = timer;
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
