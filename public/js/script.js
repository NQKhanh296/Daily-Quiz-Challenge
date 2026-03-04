let currentWords = [];
let currentQuestion = 0;
let score = 0;
let timer = 0;
let timerInterval;

document.addEventListener("DOMContentLoaded", function () {
  const savedWords = localStorage.getItem("quizLoginWords");
  if (savedWords) {
    document.getElementById("manualWords").value = savedWords;
  }
  generateNewWords();
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

async function authenticateUser() {
  let words = [];
  const manualInput = document.getElementById("manualWords").value.trim();

  words = manualInput ? manualInput.split(/[\s,]+/).filter((w) => w.length > 0) : currentWords;

  if (words.length !== 3) {
    alert("Musíš zadat přesně 3 slova!");
    return;
  }

  try {
    const response = await fetch("/api/authenticate", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ words: words }),
      credentials: 'include',
    });

    if (response.ok) {
      localStorage.setItem("quizLoginWords", words.join(" "));
      showScreen("startScreen");
      loadTodayQuiz();
    } else {
      const data = await response.json();
      alert("Chyba: " + data.error);
    }
  } catch (e) {
    alert("Server neodpovídá.");
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
  }
}

function renderQuestion(questionData) {
  currentQuestion++;
  document.getElementById("questionNum").textContent = currentQuestion;
  document.getElementById("questionText").textContent = questionData.text;
  document.getElementById("nextBtn").classList.add("hidden");

  const answersDiv = document.getElementById("answers");
  answersDiv.innerHTML = "";

  let options = typeof questionData.options === "string" ? JSON.parse(questionData.options) : questionData.options;

  options.forEach((option, index) => {
    const btn = document.createElement("button");
    btn.textContent = option;
    btn.className = "answer-btn";
    btn.onclick = () => submitAnswer(index, btn);
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
    if (data.correct) {
      clickedBtn.classList.add("correct");
    } else {
      clickedBtn.classList.add("wrong");
      if (data.correct_index !== undefined && buttons[data.correct_index]) {
        buttons[data.correct_index].classList.add("correct");
      }
    }

    if (data.earned_points) {
      score += data.earned_points;
      document.getElementById("score").textContent = score;
    }

    const nextBtn = document.getElementById("nextBtn");
    nextBtn.textContent = currentQuestion < 3 ? "Další otázka" : "Dokončit kvíz";
    nextBtn.classList.remove("hidden");
  } catch (error) { alert("Chyba při odesílání odpovědi."); }
}

function handleNextStep() {
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

let lastScore = 0;
let lastTime = 0;

function showFinalResults(totalPoints) {
  stopTimer();
  lastScore = totalPoints;
  lastTime = timer;
  document.getElementById("finalScore").textContent = lastScore;
  document.getElementById("finalTime").textContent = lastTime;
  showScreen("resultScreen");
}

async function showLeaderboard() {
  showScreen("leaderboardScreen");

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
    }

    if (data.currentUser) {
        document.getElementById("currentUserScore").textContent = data.currentUser.score;
        document.getElementById("currentUserRank").textContent = data.currentUser.rank ? data.currentUser.rank : "Neumístěn";
    }

  } catch (e) {
      listEl.innerHTML = "<li>Chyba žebříčku.</li>";
      console.error(e);
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
  location.reload();
}