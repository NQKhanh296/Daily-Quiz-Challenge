let currentWords = [];
let currentQuestion = 0;
let score = 0;
let timer = 0;
let timerInterval;
let lastScore = 0;
let lastTime = 0;

document.addEventListener("DOMContentLoaded", function () {
  const savedWords = localStorage.getItem("quizLoginWords");

  if (savedWords) {
    authenticateUser(savedWords.split(" "), true);
  } else {
    showScreen("authScreen");
    generateNewWords();
  }
});

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

function registerUser() {
  if (currentWords.length !== 3) {
    alert("Slova ještě nebyla načtena. Zkus to za chvíli.");
    return;
  }
  authenticateUser(currentWords, false);
}

function loginUser() {
  const manualInput = document.getElementById("manualWords").value.trim();
  const words = manualInput ? manualInput.split(/[\s,]+/).filter((w) => w.length > 0) : [];

  if (words.length !== 3) {
    alert("Musíš zadat přesně 3 slova oddělená mezerou!");
    return;
  }
  authenticateUser(words, false);
}

async function authenticateUser(words, isAutoLogin = false) {
  try {
    const response = await fetch("/api/authenticate", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ words: words }),
      credentials: 'include',
    });

    if (response.ok) {
      localStorage.setItem("quizLoginWords", words.join(" "));
      loadTodayQuiz();

      const lastPlayed = localStorage.getItem("lastPlayedDate");
      const today = new Date().toDateString();

      if (lastPlayed === today) {
        showLeaderboard();
      } else {
        showScreen("startScreen");
      }
    } else {
      if (isAutoLogin) {
        localStorage.removeItem("quizLoginWords");
        showScreen("authScreen");
        generateNewWords();
      } else {
        const data = await response.json();
        alert("Chyba: " + (data.error || "Neplatná slova."));
      }
    }
  } catch (e) {
    if (!isAutoLogin) alert("Server neodpovídá.");
  }
}

async function loadTodayQuiz() {
  try {
    const response = await fetch("/api/quiz/today");
    if (!response.ok) return;
    const data = await response.json();
    const topicElement = document.querySelector("#todayTopic span");
    if (topicElement) topicElement.textContent = data.topic || "Neznámé téma";
  } catch (error) { console.error(error); }
}

async function startQuiz(selectedDifficulty) {
  try {
    const response = await fetch("/api/quiz/start", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ difficulty: parseInt(selectedDifficulty) }),
    });

    if (!response.ok) {
      const errorData = await response.json();
      localStorage.setItem("lastPlayedDate", new Date().toDateString());
      throw new Error(errorData.error || "Již jsi dnes hrál.");
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
    if (error.message.includes("hrál") || error.message.includes("played")) {
      showLeaderboard();
    }
  }
}

function renderQuestion(questionData) {
  currentQuestion++;
  document.getElementById("questionNum").textContent = currentQuestion;
  document.getElementById("questionText").textContent = questionData.text;

  const nextBtn = document.getElementById("nextBtn");
  nextBtn.classList.add("hidden");

  const answersDiv = document.getElementById("answers");
  answersDiv.innerHTML = "";

  let options = typeof questionData.options === "string" ? JSON.parse(questionData.options) : questionData.options;

  let shuffledOptions = options.map((text, index) => ({
    text: text,
    originalIndex: index
  })).sort(() => Math.random() - 0.5);

  shuffledOptions.forEach((opt) => {
    const btn = document.createElement("button");
    btn.textContent = opt.text;
    btn.className = "answer-btn";
    btn.dataset.originalIndex = opt.originalIndex;

    btn.onclick = () => submitAnswer(opt.originalIndex, btn);
    answersDiv.appendChild(btn);
  });
}

async function submitAnswer(answerIndex, clickedBtn) {
  const buttons = document.querySelectorAll(".answer-btn");

  buttons.forEach((btn) => (btn.disabled = true));

  try {
    const response = await fetch("/api/quiz/submit-answer", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ answer_index: answerIndex }),
    });

    const data = await response.json();
    console.log("Odpověď ze serveru:", data);

    if (data.correct) {
      clickedBtn.classList.add("correct");
    } else {
      clickedBtn.classList.add("wrong");

      buttons.forEach(btn => {
        if (parseInt(btn.dataset.originalIndex) === data.correct_index) {
          btn.classList.add("correct");
        }
      });
    }

    if (data.earned_points !== undefined) {
      score += data.earned_points;
      document.getElementById("score").textContent = score;
    }

    const nextBtn = document.getElementById("nextBtn");
    nextBtn.textContent = currentQuestion < 3 ? "Další otázka" : "Dokončit kvíz";
    nextBtn.classList.remove("hidden");

  } catch (error) {
    console.error("Chyba:", error);
    alert("Chyba při odesílání odpovědi.");
  }
}

function handleNextStep() {
  document.getElementById("nextBtn").classList.add("hidden");

  if (currentQuestion < 3) {
    loadNextQuestion();
  } else {
    finishQuiz();
  }
}

async function loadNextQuestion() {
  const response = await fetch("/api/quiz/fetch-question");
  const data = await response.json();
  if (data.status === "finished") {
    showFinalResults(data.total_points);
  } else {
    renderQuestion(data);
  }
}

async function finishQuiz() {
  const response = await fetch("/api/quiz/fetch-question");
  const data = await response.json();
  showFinalResults(data.total_points || score);
}

function showFinalResults(totalPoints) {
  stopTimer();
  lastScore = totalPoints;
  lastTime = timer;

  localStorage.setItem("lastPlayedDate", new Date().toDateString());

  document.getElementById("finalScore").textContent = lastScore;
  document.getElementById("finalTime").textContent = lastTime;
  showScreen("resultScreen");
}

async function showLeaderboard() {
  showScreen("leaderboardScreen");

  const lastPlayed = localStorage.getItem("lastPlayedDate");
  const today = new Date().toDateString();
  const btnBack = document.getElementById("btnBackToMenu");

  if (btnBack) {
    if (lastPlayed === today) {
      btnBack.style.display = "none";
    } else {
      btnBack.style.display = "inline-block";
    }
  }

  document.getElementById("playerCurrentAttempt").textContent = lastScore;
  document.getElementById("playerCurrentTime").textContent = lastTime;

  const listEl = document.getElementById("leaderboardList");
  listEl.innerHTML = "<li>Načítám...</li>";

  try {
    const response = await fetch("/api/leaderboard");
    const data = await response.json();
    listEl.innerHTML = "";

    if (data.top10 && Array.isArray(data.top10)) {
        data.top10.forEach((user, index) => {
          const li = document.createElement("li");
          li.className = "leaderboard-item";
          li.innerHTML = `<span class="rank ${index < 3 ? 'top'+(index+1) : ''}">${index+1}</span>
                          <span>${user.username}</span>
                          <span>${user.score}</span>`;
          listEl.appendChild(li);
        });

        if (data.top10.length === 0) {
          listEl.innerHTML = "<li>Zatím žádné výsledky. Buď první!</li>";
        }
    }

    if (data.currentUser) {
        const scoreEl = document.getElementById("currentUserScore");
        const rankEl = document.getElementById("currentUserRank");
        if (scoreEl) scoreEl.textContent = data.currentUser.score;
        if (rankEl) rankEl.textContent = data.currentUser.rank ? data.currentUser.rank : "Neumístěn";
    }

  } catch (e) {
      listEl.innerHTML = "<li>Chyba žebříčku.</li>";
      console.error("Leaderboard chyba:", e);
  }
}

async function changeUsername() {
  const input = document.getElementById("newUsernameInput");
  const newUsername = input.value.trim();

  const nameRegex = /^[a-zA-Z0-9_ěščřžýáíéúůóťďňĚŠČŘŽÝÁÍÉÚŮÓŤĎŇ]{3,20}$/;

  if (!newUsername) {
    alert("Zadej nové jméno!");
    return;
  }

  if (!nameRegex.test(newUsername)) {
    alert("Jméno musí mít 3 až 20 znaků a nesmí obsahovat speciální znaky (pouze písmena, čísla a podtržítka).");
    return;
  }

  const response = await fetch('/api/user/change-username', {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({ username: newUsername }),
    credentials: 'include'
  });

  const data = await response.json();

  if (response.status === 200) {
    alert("Jméno bylo změněno!");
    input.value = "";
    document.getElementById("changeUsernameForm").classList.add("hidden");
    showLeaderboard();
  } else if (response.status === 429) {
    alert("Příliš mnoho pokusů, zkus to za 15 minut.");
  } else if (response.status === 422) {
    alert("Chyba validace: " + data.errors);
  } else if (response.status === 401) {
    alert("Nejsi přihlášen.");
    location.reload();
  } else {
    alert("Chyba: " + (data.error || "Neznámá chyba"));
  }
}

function toggleUsernameForm() {
  const form = document.getElementById("changeUsernameForm");
  form.classList.toggle("hidden");
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

function stopTimer() { clearInterval(timerInterval); }

function showScreen(screenId) {
  document.querySelectorAll(".screen").forEach(s => s.classList.add("hidden"));
  document.getElementById(screenId).classList.remove("hidden");
}

function logout() {
  localStorage.removeItem("quizLoginWords");
  localStorage.removeItem("lastPlayedDate");
  location.reload();
}