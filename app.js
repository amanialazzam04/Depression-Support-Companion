(function () {
  "use strict";

  const CRISIS_PATTERNS = [
    /\b(kill myself|end it all|suicid|want to die|better off dead)\b/i,
    /\b(no reason to live|can't go on)\b/i,
    /\b(self[- ]harm|hurt myself|cut myself)\b/i,
  ];

  const EMOTION_LEXICON = {
    depression: [
      "depress",
      "depressed",
      "depression",
      "hopeless",
      "empty",
      "numb",
      "nothing matters",
      "can't get up",
      "cant get up",
      "no energy",
      "exhausted",
      "worthless",
      "useless",
      "guilt",
      "guilty",
      "lonely",
      "isolated",
      "cry",
      "crying",
      "grief",
      "down",
      "low",
      "sad",
      "don't care",
      "dont care",
      "giving up",
    ],
    anxiety: [
      "anxious",
      "panic",
      "worried",
      "nervous",
      "stress",
      "stressed",
      "overwhelm",
      "overwhelmed",
      "fear",
      "scared",
      "racing heart",
      "can't sleep",
      "cant sleep",
    ],
    anger: ["angry", "furious", "rage", "hate", "frustrated", "annoyed"],
    hope: ["hope", "better", "grateful", "okay", "ok", "improving", "proud", "bit better"],
  };

  const RESPONSES = {
    depression: [
      "Depression can make everything feel heavy or far away — what you're describing is real, and you're not weak for feeling it.",
      "Thank you for saying it out loud. You don't have to fix everything today; sometimes the bravest thing is a tiny act of care (water, light, one message).",
      "Low mood often lies about your worth. Even if you don't believe it yet, reaching here means part of you still cares — hold onto that gently.",
      "When motivation is gone, 'small and kind' beats 'perfect.' One step you can actually do is enough for this hour.",
    ],
    anxiety: [
      "Anxiety often shows up alongside depression — a wired mind and a tired body at once. Slow breathing or naming what you see can ease the edge a little.",
      "You don't have to calm down all at once. One minute of grounding is still progress.",
    ],
    anger: [
      "Anger can sit under depression when life feels unfair or stuck. Your feelings make sense — stay safe, and let the intensity pass before big decisions.",
    ],
    hope: [
      "I'm glad something in you noticed a little relief or hope. Those moments matter, even when they're small.",
      "Healing from depression isn't linear. If today had a slightly lighter moment, that's worth noticing.",
    ],
    mixed: [
      "Depression rarely shows up as one pure feeling — numbness, worry, and tiny hope can coexist. Whatever mix you're in, you can still move one small step at a time.",
      "Thank you for sharing. Your experience doesn't have to sound 'logical' to be valid.",
    ],
    default: [
      "Thank you for trusting this space. Living with low mood is hard — you deserve support, including from professionals when you're ready.",
      "I'm here to respond with care. If depression has been heavy for a long time or you're unsure what to do, talking to a doctor or therapist is a strong next step.",
    ],
  };

  const AFFIRMATIONS = [
    "Depression is an illness many people recover from — help is real.",
    "You deserve care, including from yourself.",
    "Rest is not something you have to earn.",
    "Small steps still count on heavy days.",
    "Asking for help is brave, not a failure.",
    "This chapter doesn't have to be your whole story.",
  ];

  function moodStorageKey() {
    const u =
      typeof window !== "undefined" && window.__USER_NAME__
        ? String(window.__USER_NAME__).trim()
        : "";
    return u ? `mindfulCompanionMood:${u}` : "mindfulCompanionMood";
  }

  /** Last N turns sent to the API (user + assistant only). */
  const MAX_API_MESSAGES = 20;
  let chatHistory = [];

  const STORAGE_API_BASE = "mindfulCompanionApiBase";
  const DEFAULT_FILE_API = "http://127.0.0.1:8000";

  /**
   * When you open index.html as a file, relative URLs like /api/... fail.
   * Use the local server URL so the browser can reach the backend.
   */
  function getApiBase() {
    try {
      const saved = localStorage.getItem(STORAGE_API_BASE);
      if (saved && String(saved).trim()) {
        return String(saved).trim().replace(/\/$/, "");
      }
    } catch (_) {}
    if (typeof window === "undefined") return "";
    if (window.location.protocol === "file:") return DEFAULT_FILE_API;
    return "";
  }

  function apiUrl(path) {
    const base = getApiBase();
    if (!base) return path;
    return base + path;
  }

  function normalizeText(t) {
    return (t || "").toLowerCase().trim();
  }

  function detectCrisis(text) {
    const n = normalizeText(text);
    return CRISIS_PATTERNS.some((re) => re.test(n));
  }

  function analyzeEmotion(text) {
    const n = normalizeText(text);
    const scores = { depression: 0, anxiety: 0, anger: 0, hope: 0 };
    for (const [emotion, words] of Object.entries(EMOTION_LEXICON)) {
      for (const w of words) {
        if (n.includes(w)) scores[emotion]++;
      }
    }
    const entries = Object.entries(scores).filter(([, v]) => v > 0);
    if (entries.length === 0) return { primary: null, label: null };
    entries.sort((a, b) => b[1] - a[1]);
    const primary = entries[0][0];
    const label =
      primary === "depression"
        ? "Detected tone: low mood / depression"
        : primary === "anxiety"
          ? "Detected tone: worry / anxiety"
          : primary === "anger"
            ? "Detected tone: frustration / anger"
            : primary === "hope"
              ? "Detected tone: hope / steadiness"
              : null;
    return { primary, label };
  }

  function countEmotionCategories(text) {
    const n = normalizeText(text);
    let categoriesWithHits = 0;
    for (const words of Object.values(EMOTION_LEXICON)) {
      if (words.some((w) => n.includes(w))) categoriesWithHits++;
    }
    return categoriesWithHits;
  }

  function pickResponse(emotionResult, userText) {
    const { primary } = emotionResult;
    let pool = RESPONSES.default;
    if (primary && RESPONSES[primary]) {
      const mixed = countEmotionCategories(userText) >= 2;
      pool = mixed ? RESPONSES.mixed : RESPONSES[primary];
    }
    return pool[Math.floor(Math.random() * pool.length)];
  }

  /**
   * @param {"ai"|"rules"|null} replySource — show label so you can see AI vs built-in.
   */
  function appendMessage(role, text, emotionLabel, replySource) {
    const container = document.getElementById("chat-messages");
    const wrap = document.createElement("div");
    wrap.className = "msg msg--" + (role === "bot" ? "bot" : "user");
    const bubble = document.createElement("div");
    bubble.className = "msg__bubble";
    bubble.textContent = text;
    wrap.appendChild(bubble);
    if (role === "bot" && emotionLabel) {
      const meta = document.createElement("div");
      meta.className = "msg__emotion";
      meta.textContent = emotionLabel;
      wrap.appendChild(meta);
    }
    if (role === "bot" && replySource) {
      const src = document.createElement("div");
      src.className = "msg__source";
      src.textContent =
        replySource === "ai" ? "OpenAI reply" : "Built-in reply";
      wrap.appendChild(src);
    }
    const time = document.createElement("div");
    time.className = "msg__meta";
    time.textContent = new Date().toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
    });
    wrap.appendChild(time);
    container.appendChild(wrap);
    container.scrollTop = container.scrollHeight;
  }

  function showTypingIndicator() {
    const container = document.getElementById("chat-messages");
    const wrap = document.createElement("div");
    wrap.className = "msg msg--bot msg--typing";
    wrap.setAttribute("aria-live", "polite");
    const bubble = document.createElement("div");
    bubble.className = "msg__bubble";
    bubble.textContent = "Thinking…";
    wrap.appendChild(bubble);
    container.appendChild(wrap);
    container.scrollTop = container.scrollHeight;
    return function removeTyping() {
      wrap.remove();
    };
  }

  function showCrisisBanner() {
    const banner = document.getElementById("crisis-banner");
    const p = banner.querySelector(".crisis-banner__text");
    p.textContent =
      "It sounds like you might be in real distress. You deserve immediate support from people trained to help — please reach out now:";
    banner.classList.remove("hidden");
    banner.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  /** Notify admin when a crisis alert is triggered. */
  function reportAlert(message, alertType) {
    fetch(apiUrl("/api/alerts.php"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        message,
        alert_type: alertType || "crisis",
        source: "client",
      }),
    }).catch(() => {});
  }

  async function fetchChatApi(messages) {
    const res = await fetch(apiUrl("/api/chat.php"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ messages }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const err = new Error(data.message || data.error || "request_failed");
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  }

  function setChatBusy(form, busy) {
    const btn = form.querySelector('button[type="submit"]');
    const ta = form.querySelector("#user-input");
    if (btn) btn.disabled = busy;
    if (ta) ta.disabled = busy;
  }

  async function refreshApiStatus() {
    const el = document.getElementById("api-status");
    const notice = document.getElementById("connection-notice");
    if (!el) return;

    let ok = false;
    let hasKey = false;
    try {
      const r = await fetch(apiUrl("/api/health.php"));
      if (!r.ok) throw new Error("bad");
      const j = await r.json();
      ok = true;
      hasKey = Boolean(j.hasApiKey);
      if (hasKey) {
        el.textContent =
          "Chat: OpenAI is connected — you should see “OpenAI reply” under bot messages.";
        el.classList.remove("is-offline");
      } else {
        el.textContent =
          "Server is running but OPENAI_API_KEY is missing — add it to server_py/.env (Python) or server/.env (Node) and restart. Until then, replies are built-in only.";
        el.classList.add("is-offline");
      }
    } catch {
      el.textContent =
        "Chat: built-in replies only — the API server is not reachable from this page.";
      el.classList.add("is-offline");
    }

    if (notice) {
      const isFile = typeof window !== "undefined" && window.location.protocol === "file:";
      if (isFile) {
        notice.hidden = false;
        notice.innerHTML =
          "<strong>Opened as a file:</strong> start the PHP server and open <a href=\"http://127.0.0.1:8000/\">http://127.0.0.1:8000/</a>. The chat will show “OpenAI reply” when the API is working.";
      } else if (!ok) {
        notice.hidden = false;
        notice.innerHTML =
          "<strong>API offline:</strong> start the server (Python: <code>server_py</code>) then refresh this page.";
      } else {
        notice.hidden = true;
      }
    }
  }

  function initChat() {
    const form = document.getElementById("chat-form");
    const input = document.getElementById("user-input");
    chatHistory = [];

    appendMessage(
      "bot",
      "Hi — I'm a supportive companion focused on depression and low mood. I'm not a therapist and I can't diagnose you. Share what feels right; I'll respond with empathy. If you're in crisis, please reach out for real-world help — you matter.",
      null
    );

    refreshApiStatus();

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const raw = input.value.trim();
      if (!raw) return;
      appendMessage("user", raw, null);
      input.value = "";

      if (detectCrisis(raw)) {
        showCrisisBanner();
        reportAlert(raw, "crisis");
        appendMessage(
          "bot",
          "I'm really glad you said something. What you're describing is serious. Please contact a crisis line or emergency services now — you don't have to go through this alone.",
          null
        );
        return;
      }

      chatHistory.push({ role: "user", content: raw });
      const payload = chatHistory.slice(-MAX_API_MESSAGES);

      const removeTyping = showTypingIndicator();
      setChatBusy(form, true);
      try {
        const data = await fetchChatApi(payload);
        removeTyping();

        if (data.crisis) {
          showCrisisBanner();
          // Server already logs the alert; no duplicate report needed.
          appendMessage("bot", data.reply, null, null);
          chatHistory.push({ role: "assistant", content: data.reply });
          return;
        }

        if (data.reply) {
          appendMessage("bot", data.reply, null, "ai");
          chatHistory.push({ role: "assistant", content: data.reply });
          return;
        }
      } catch (err) {
        removeTyping();
        if (err.status === 503 && err.data && err.data.error === "no_api_key") {
          /* fall through to local */
        } else if (err.status !== 503) {
          console.warn("Chat API:", err.message);
        }
      } finally {
        setChatBusy(form, false);
      }

      const emotion = analyzeEmotion(raw);
      const reply = pickResponse(emotion, raw);
      appendMessage("bot", reply, emotion.label, "rules");
      chatHistory.push({ role: "assistant", content: reply });
    });
  }

  function loadMoods() {
    try {
      const raw = localStorage.getItem(moodStorageKey());
      return raw ? JSON.parse(raw) : [];
    } catch {
      return [];
    }
  }

  function saveMoods(entries) {
    try {
      localStorage.setItem(moodStorageKey(), JSON.stringify(entries.slice(-60)));
    } catch {
      /* ignore quota */
    }
  }

  function moodLabel(score) {
    const map = {
      1: "Very low",
      2: "Low",
      3: "Okay",
      4: "Good",
      5: "Great",
    };
    return map[String(score)] || score;
  }

  function renderMoodList() {
    const list = document.getElementById("mood-list");
    const entries = loadMoods().slice().reverse();
    list.innerHTML = "";
    if (entries.length === 0) {
      const li = document.createElement("li");
      li.textContent = "No entries yet — save a check-in to see your history.";
      list.appendChild(li);
      return;
    }
    for (const e of entries) {
      const li = document.createElement("li");
      const date = document.createElement("span");
      date.className = "date";
      date.textContent = e.date;
      const score = document.createElement("span");
      score.className = "score";
      score.textContent = moodLabel(e.score);
      const note = document.createElement("span");
      note.textContent = e.note ? ` — ${e.note}` : "";
      li.appendChild(date);
      li.appendChild(score);
      li.appendChild(note);
      list.appendChild(li);
    }
  }

  function initMood() {
    const form = document.getElementById("mood-form");
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const score = parseInt(fd.get("mood"), 10);
      const note = (fd.get("note") || "").toString().trim();
      const date = new Date().toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
      const isoDate = new Date().toISOString().slice(0, 10);
      const entries = loadMoods();
      entries.push({ date, score, note });
      saveMoods(entries);
      renderMoodList();
      form.querySelector("#mood-note").value = "";

      // Best-effort: also send to server so admin can review.
      // If the API isn't running, local history still works.
      fetch(apiUrl("/api/moods.php"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ score, note, date: isoDate }),
      }).catch(() => {});
    });
    renderMoodList();
  }

  function initNav() {
    const buttons = document.querySelectorAll(".nav-btn");
    const panels = {
      chat: document.getElementById("panel-chat"),
      mood: document.getElementById("panel-mood"),
      wellness: document.getElementById("panel-wellness"),
      about: document.getElementById("panel-about"),
    };
    buttons.forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-panel");
        buttons.forEach((b) => b.classList.toggle("is-active", b === btn));
        Object.entries(panels).forEach(([key, el]) => {
          const show = key === id;
          el.hidden = !show;
          el.classList.toggle("is-visible", show);
        });
      });
    });
  }

  function initAffirmations() {
    const el = document.getElementById("affirmation-text");
    const btn = document.getElementById("new-affirmation");
    function next() {
      el.textContent =
        AFFIRMATIONS[Math.floor(Math.random() * AFFIRMATIONS.length)];
    }
    btn.addEventListener("click", next);
  }

  document.addEventListener("DOMContentLoaded", () => {
    initNav();
    initChat();
    initMood();
    initAffirmations();
  });
})();
