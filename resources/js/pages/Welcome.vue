<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, nextTick, onMounted } from 'vue';
import { login, register } from '@/routes';

type Message = {
    role: 'user' | 'assistant';
    content: string;
};

type Conversation = {
    id: number;
    title: string;
    messages: Message[];
};

const conversations = ref<Conversation[]>([]);
const activeConversationId = ref<number | null>(null);
const prompt = ref('');
const isLoading = ref(false);
const textarea = ref<HTMLTextAreaElement | null>(null);

const activeConversation = ref<Conversation | null>(null);

function updateActiveConversation() {
    activeConversation.value =
        conversations.value.find(
            (conversation) => conversation.id === activeConversationId.value,
        ) ?? null;
}

/**
 * Loads the conversation list from the database.
 */
async function loadConversations() {
    try {
        const response = await fetch('/chat/conversations', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to load conversations.');
        }

        const data = await response.json();

        conversations.value = (data.conversations ?? []).map(
            (conversation: { id: number; title: string }) => ({
                id: conversation.id,
                title: conversation.title,
                messages: [],
            }),
        );
    } catch (error) {
        console.error('Conversation loading error:', error);
    }
}

/**
 * Creates a new conversation without saving to the database yet.
 * Saving happens when the first message is sent.
 */
function newChat() {
    activeConversationId.value = null;
    activeConversation.value = null;
    prompt.value = '';

    nextTick(() => textarea.value?.focus());
}

/**
 * Loads the complete history of a selected conversation.
 */
async function selectConversation(id: number) {
    activeConversationId.value = id;
    updateActiveConversation();

    const existingConversation = conversations.value.find(
        (conversation) => conversation.id === id,
    );

    if (!existingConversation) {
        return;
    }

    // If the history already exists in memory, no new request is needed.
    if (existingConversation.messages.length > 0) {
        return;
    }

    try {
        const response = await fetch(`/chat/conversations/${id}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to load conversation history.');
        }

        const data = await response.json();
        const loadedConversation = data.conversation;

        // Update the history of the specific conversation.
        const conversation = conversations.value.find((item) => item.id === id);

        if (!conversation || !loadedConversation) {
            return;
        }

        conversation.title = loadedConversation.title;
        conversation.messages = (loadedConversation.messages ?? []).map(
            (message: Message) => ({
                role: message.role,
                content: message.content,
            }),
        );

        // Don't change the active conversation if the user
        // has selected another while the history was loading.
        if (activeConversationId.value === id) {
            updateActiveConversation();
        }
    } catch (error) {
        console.error('Conversation history error:', error);
    }
}

/**
 * Sends the history to Gemini and saves
 * new messages via the ChatController.
 */
async function sendMessage() {
    const content = prompt.value.trim();

    if (!content || isLoading.value) return;

    let conversation = activeConversation.value;

    if (!conversation) {
        conversation = {
            // Negative ID for temporary conversation that hasn't
            // been saved to the database yet.
            id: -Date.now(),
            title: content.length > 35 ? `${content.slice(0, 35)}...` : content,
            messages: [],
        };

        conversations.value.unshift(conversation);
        activeConversationId.value = conversation.id;
        activeConversation.value = conversation;
    }

    conversation.messages.push({
        role: 'user',
        content,
    });

    prompt.value = '';
    isLoading.value = true;

    try {
        const csrfToken = document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;

        const response = await fetch('/chat', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({
                // Send ID only when the conversation has
                // already been saved to the database.
                ...(conversation.id > 0
                    ? { conversation_id: conversation.id }
                    : {}),
                messages: conversation.messages.map((message) => ({
                    role: message.role,
                    content: message.content,
                })),
            }),
        });

        const data = await response.json();

        // The backend returns the real database ID.
        // This is important even in case of an error,
        // as long as the backend returns the conversation_id.
        if (
            Number.isInteger(data.conversation_id) &&
            data.conversation_id > 0
        ) {
            conversation.id = data.conversation_id;
            activeConversationId.value = data.conversation_id;

            if (typeof data.title === 'string') {
                conversation.title = data.title;
            }
        }

        if (!response.ok) {
            throw new Error(
                data.message ||
                    'An error occurred while communicating with the AI.',
            );
        }

        if (typeof data.reply !== 'string' || !data.reply.trim()) {
            throw new Error('The AI did not return a response.');
        }

        conversation.messages.push({
            role: 'assistant',
            content: data.reply,
        });
    } catch (error) {
        const errorMessage =
            error instanceof Error
                ? error.message
                : 'Could not communicate with the AI.';

        conversation.messages.push({
            role: 'assistant',
            content: `Error: ${errorMessage}`,
        });
    } finally {
        isLoading.value = false;
        updateActiveConversation();
    }
}

/**
 * Deletes a conversation after confirmation.
 */
async function deleteConversation(conversation: Conversation) {
    const confirmed = window.confirm(
        `Are you sure you want to delete the conversation "${conversation.title}";`,
    );

    if (!confirmed) {
        return;
    }

    if (isLoading.value) {
        window.alert('Wait for the AI response to complete.');
        return;
    }

    try {
        // Temporary conversations don't exist in the database yet.
        if (conversation.id > 0) {
            const csrfToken = document.querySelector<HTMLMetaElement>(
                'meta[name="csrf-token"]',
            )?.content;

            const response = await fetch(
                `/chat/conversations/${conversation.id}`,
                {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                },
            );

            const data: { message?: string } = await response
                .json()
                .catch(() => ({}));

            if (!response.ok) {
                throw new Error(
                    data.message || 'Could not delete the conversation.',
                );
            }
        }

        // Remove the conversation from the sidebar.
        conversations.value = conversations.value.filter(
            (item) => item.id !== conversation.id,
        );

        // If it was active, return to the initial screen.
        if (activeConversationId.value === conversation.id) {
            activeConversationId.value = null;
            activeConversation.value = null;
            prompt.value = '';

            nextTick(() => textarea.value?.focus());
        }
    } catch (error) {
        window.alert(
            error instanceof Error
                ? error.message
                : 'An error occurred while deleting.',
        );
    }
}

// Load saved conversations when the page opens.
onMounted(() => {
    loadConversations();
});
</script>

<template>
    <Head title="Glass AI" />

    <div class="app-shell">
        <div class="ambient ambient-one"></div>
        <div class="ambient ambient-two"></div>
        <div class="ambient ambient-three"></div>
        <div class="noise-layer"></div>

        <div class="app-layout">
            <!-- SIDEBAR -->
            <aside class="sidebar glass-panel">
                <button class="brand-button" @click="newChat">
                    <span class="brand-icon">
                        <svg viewBox="0 0 40 40" fill="none" aria-hidden="true">
                            <path
                                d="M20 4L23.7 15.8L36 20L23.7 24.2L20 36L16.3 24.2L4 20L16.3 15.8L20 4Z"
                                fill="currentColor"
                            />
                            <circle cx="31" cy="8" r="3" fill="#B9A7FF" />
                        </svg>
                    </span>

                    <span class="brand-copy">
                        <span class="brand-name">Glass AI</span>
                        <span class="brand-caption">YOUR AI SPACE</span>
                    </span>

                    <span class="brand-plus">＋</span>
                </button>

                <button class="new-chat-button" @click="newChat">
                    <span class="new-chat-icon">＋</span>
                    <span>New conversation</span>
                </button>

                <div class="sidebar-section-label">
                    <span>WORKSPACE</span>
                    <span class="section-dot"></span>
                </div>

                <div class="conversation-list">
                    <p class="sidebar-subheading">Recent conversations</p>

                    <div
                        v-for="conversation in conversations"
                        :key="conversation.id"
                        class="conversation-item"
                        :class="{
                            'conversation-active':
                                activeConversationId === conversation.id,
                        }"
                    >
                        <button
                            type="button"
                            class="conversation-select-button"
                            :aria-label="`Open conversation ${conversation.title}`"
                            @click="selectConversation(conversation.id)"
                        >
                            <svg
                                class="conversation-icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.6"
                                aria-hidden="true"
                            >
                                <path
                                    d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"
                                />
                            </svg>

                            <span class="conversation-title">
                                {{ conversation.title }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="delete-conversation-button"
                            :aria-label="`Delete conversation ${conversation.title}`"
                            title="Delete conversation"
                            @click.stop="deleteConversation(conversation)"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M3 6h18" />
                                <path d="M8 6V4h8v2" />
                                <path d="m19 6-1 14H6L5 6" />
                                <path d="M10 11v5M14 11v5" />
                            </svg>
                        </button>
                    </div>

                    <div
                        v-if="conversations.length === 0"
                        class="empty-history"
                    >
                        <span class="empty-history-icon">✳</span>
                        <span>Your conversations will appear here.</span>
                    </div>
                </div>

                <div class="sidebar-bottom">
                    <div class="sidebar-divider"></div>

                    <div class="account-card">
                        <div class="account-avatar">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                aria-hidden="true"
                            >
                                <circle cx="12" cy="8" r="4" />
                                <path d="M4 21a8 8 0 0 1 16 0" />
                            </svg>
                        </div>

                        <div class="account-copy">
                            <span class="account-name">
                                {{ $page.props.auth.user?.name ?? 'Guest' }}
                            </span>
                            <span class="account-caption">
                                {{
                                    $page.props.auth.user
                                        ? 'Personal account'
                                        : 'Welcome to Glass AI'
                                }}
                            </span>
                        </div>

                        <svg
                            class="account-chevron"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <path d="m9 18 6-6-6-6" />
                        </svg>
                    </div>

                    <Link
                        v-if="$page.props.auth.user"
                        href="/dashboard"
                        class="account-link"
                    >
                        Open account
                    </Link>

                    <Link
                        v-if="$page.props.auth.user"
                        href="/logout"
                        method="post"
                        as="button"
                        class="account-link"
                    >
                        Sign out
                    </Link>

                    <template v-else>
                        <Link :href="login()" class="account-link">
                            Sign in
                        </Link>
                        <Link :href="register()" class="account-link">
                            Create account
                        </Link>
                    </template>

                    <div class="sidebar-footer">
                        <span class="footer-status-dot"></span>
                        <span>Glass AI</span>
                        <span class="footer-version">PREVIEW</span>
                    </div>
                </div>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="main-panel">
                <header class="topbar">
                    <div class="topbar-left">
                        <span class="topbar-symbol">✳</span>
                        <span class="topbar-title">Glass AI</span>
                        <span class="topbar-separator">/</span>
                        <span class="topbar-context">
                            {{
                                activeConversation
                                    ? 'Conversation'
                                    : 'New start'
                            }}
                        </span>
                    </div>

                    <div class="topbar-right">
                        <div class="model-badge">
                            <span class="model-status-dot"></span>
                            <span>Gemini</span>
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path d="m7 10 5 5 5-5" />
                            </svg>
                        </div>

                        <Link
                            v-if="!$page.props.auth.user"
                            :href="login()"
                            class="topbar-login"
                        >
                            Sign in
                            <span>↗</span>
                        </Link>
                    </div>
                </header>

                <!-- EMPTY STATE -->
                <section v-if="!activeConversation" class="welcome-screen">
                    <div class="welcome-content">
                        <div class="hero-orb">
                            <div class="orb-ring orb-ring-one"></div>
                            <div class="orb-ring orb-ring-two"></div>
                            <div class="orb-core">
                                <svg
                                    viewBox="0 0 64 64"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M32 5L38.5 25.5L59 32L38.5 38.5L32 59L25.5 38.5L5 32L25.5 25.5L32 5Z"
                                        fill="url(#hero-gradient)"
                                    />
                                    <defs>
                                        <linearGradient
                                            id="hero-gradient"
                                            x1="12"
                                            y1="8"
                                            x2="54"
                                            y2="57"
                                            gradientUnits="userSpaceOnUse"
                                        >
                                            <stop stop-color="#FFFFFF" />
                                            <stop
                                                offset="0.45"
                                                stop-color="#D9D0FF"
                                            />
                                            <stop
                                                offset="1"
                                                stop-color="#8E9DFF"
                                            />
                                        </linearGradient>
                                    </defs>
                                </svg>
                            </div>
                            <span class="orb-spark orb-spark-one"></span>
                            <span class="orb-spark orb-spark-two"></span>
                        </div>

                        <div class="welcome-eyebrow">
                            <span class="eyebrow-line"></span>
                            <span>A NEW AI EXPERIENCE</span>
                            <span class="eyebrow-line"></span>
                        </div>

                        <h1 class="welcome-title">
                            Your thoughts,
                            <span>without limits.</span>
                        </h1>

                        <p class="welcome-description">
                            Ideas, questions, and creation.
                            <br class="desktop-break" />
                            Start a conversation and see where it leads you.
                        </p>

                        <div class="prompt-glass">
                            <textarea
                                ref="textarea"
                                v-model="prompt"
                                rows="3"
                                placeholder="What's on your mind?"
                                aria-label="Your message"
                                class="prompt-textarea"
                                @keydown.enter.exact.prevent="sendMessage"
                            />

                            <div class="prompt-toolbar">
                                <div class="prompt-hint">
                                    <span class="hint-key">↵</span>
                                    <span>to send</span>
                                    <span class="hint-divider">·</span>
                                    <span>Shift + ↵ for new line</span>
                                </div>

                                <button
                                    class="send-button"
                                    :class="{
                                        'send-button-ready':
                                            prompt.trim() && !isLoading,
                                    }"
                                    :disabled="!prompt.trim() || isLoading"
                                    aria-label="Send message"
                                    @click="sendMessage"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path d="M12 19V5M5 12l7-7 7 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="suggestion-label">YOU CAN START WITH</div>

                        <div class="suggestion-grid">
                            <button
                                class="suggestion-card"
                                @click="
                                    prompt = 'Help me organize my ideas.';
                                    nextTick(() => textarea?.focus());
                                "
                            >
                                <span class="suggestion-icon suggestion-violet"
                                    >✳</span
                                >
                                <span class="suggestion-copy">
                                    <span class="suggestion-title"
                                        >Organize ideas</span
                                    >
                                    <span class="suggestion-description"
                                        >Put your thoughts in order.</span
                                    >
                                </span>
                                <span class="suggestion-arrow">↗</span>
                            </button>

                            <button
                                class="suggestion-card"
                                @click="
                                    prompt =
                                        'Explain a difficult topic to me in simple terms.';
                                    nextTick(() => textarea?.focus());
                                "
                            >
                                <span class="suggestion-icon suggestion-blue"
                                    >⌘</span
                                >
                                <span class="suggestion-copy">
                                    <span class="suggestion-title"
                                        >Learning and knowledge</span
                                    >
                                    <span class="suggestion-description"
                                        >Understand something new.</span
                                    >
                                </span>
                                <span class="suggestion-arrow">↗</span>
                            </button>

                            <button
                                class="suggestion-card"
                                @click="
                                    prompt = 'Help me write a text.';
                                    nextTick(() => textarea?.focus());
                                "
                            >
                                <span class="suggestion-icon suggestion-pink"
                                    >✎</span
                                >
                                <span class="suggestion-copy">
                                    <span class="suggestion-title"
                                        >Creation</span
                                    >
                                    <span class="suggestion-description"
                                        >Turn an idea into text.</span
                                    >
                                </span>
                                <span class="suggestion-arrow">↗</span>
                            </button>

                            <button
                                class="suggestion-card"
                                @click="
                                    prompt =
                                        'Help me find a solution to a problem.';
                                    nextTick(() => textarea?.focus());
                                "
                            >
                                <span class="suggestion-icon suggestion-cyan"
                                    >◎</span
                                >
                                <span class="suggestion-copy">
                                    <span class="suggestion-title"
                                        >Problem solving</span
                                    >
                                    <span class="suggestion-description"
                                        >See the issue from another
                                        perspective.</span
                                    >
                                </span>
                                <span class="suggestion-arrow">↗</span>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- CHAT -->
                <section v-else class="chat-screen">
                    <div class="messages-scroll">
                        <div class="messages-inner">
                            <article
                                v-for="(
                                    message, index
                                ) in activeConversation.messages"
                                :key="index"
                                class="message-row"
                                :class="
                                    message.role === 'user'
                                        ? 'message-user'
                                        : 'message-assistant'
                                "
                                :style="{ '--message-index': index }"
                            >
                                <template v-if="message.role === 'assistant'">
                                    <div class="message-avatar">
                                        <svg
                                            viewBox="0 0 32 32"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M16 3L19.5 12.5L29 16L19.5 19.5L16 29L12.5 19.5L3 16L12.5 12.5L16 3Z"
                                                fill="currentColor"
                                            />
                                        </svg>
                                    </div>

                                    <div class="message-body">
                                        <div class="message-author">
                                            Glass AI
                                        </div>
                                        <div class="message-text">
                                            {{ message.content }}
                                        </div>
                                    </div>
                                </template>

                                <div v-else class="user-message-bubble">
                                    {{ message.content }}
                                </div>
                            </article>

                            <div
                                v-if="isLoading"
                                class="message-row message-assistant"
                            >
                                <div class="message-avatar loading-avatar">
                                    <svg
                                        viewBox="0 0 32 32"
                                        fill="none"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M16 3L19.5 12.5L29 16L19.5 19.5L16 29L12.5 19.5L3 16L12.5 12.5L16 3Z"
                                            fill="currentColor"
                                        />
                                    </svg>
                                </div>

                                <div class="message-body">
                                    <div class="message-author">Glass AI</div>
                                    <div class="thinking-indicator">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                        <span class="thinking-label"
                                            >Thinking</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="chat-composer-area">
                        <div class="prompt-glass chat-prompt-glass">
                            <textarea
                                v-model="prompt"
                                rows="2"
                                placeholder="Continue the discussion..."
                                aria-label="Your message"
                                class="prompt-textarea chat-textarea"
                                @keydown.enter.exact.prevent="sendMessage"
                            />

                            <div class="prompt-toolbar">
                                <div class="prompt-hint">
                                    <span class="hint-key">↵</span>
                                    <span>to send</span>
                                </div>

                                <button
                                    class="send-button"
                                    :class="{
                                        'send-button-ready':
                                            prompt.trim() && !isLoading,
                                    }"
                                    :disabled="!prompt.trim() || isLoading"
                                    aria-label="Send message"
                                    @click="sendMessage"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path d="M12 19V5M5 12l7-7 7 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <p class="chat-disclaimer">
                            Glass AI can make mistakes. Check important
                            information.
                        </p>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<style>
:root {
    color-scheme: dark;
    font-family: Arial, Helvetica, sans-serif;
    font-synthesis: none;
    text-rendering: optimizeLegibility;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

* {
    box-sizing: border-box;
}

html,
body,
#app {
    min-height: 100%;
    margin: 0;
}

body {
    background: #080a12;
}

button,
textarea,
a {
    font: inherit;
}

button {
    -webkit-tap-highlight-color: transparent;
}

button:focus-visible,
a:focus-visible,
textarea:focus-visible {
    outline: 2px solid rgba(172, 160, 255, 0.9);
    outline-offset: 3px;
}

.app-shell {
    position: relative;
    isolation: isolate;
    width: 100%;
    height: 100vh;
    height: 100dvh;
    overflow: hidden;
    color: #f4f3ff;
    background:
        radial-gradient(
            ellipse at 50% -25%,
            rgba(92, 75, 173, 0.22),
            transparent 58%
        ),
        linear-gradient(135deg, #0b0d17 0%, #0a0b13 48%, #101020 100%);
}

.ambient {
    position: absolute;
    z-index: -2;
    width: 36rem;
    height: 36rem;
    pointer-events: none;
    border-radius: 50%;
    filter: blur(100px);
    opacity: 0.17;
    animation: ambient-float 22s ease-in-out infinite alternate;
}

.ambient-one {
    top: -19rem;
    left: 23%;
    background: #7665ff;
}

.ambient-two {
    right: -22rem;
    bottom: -18rem;
    background: #3459e8;
    animation-delay: -8s;
}

.ambient-three {
    left: 12%;
    bottom: -28rem;
    width: 30rem;
    height: 30rem;
    background: #9b54c8;
    opacity: 0.1;
    animation-delay: -14s;
}

.noise-layer {
    position: absolute;
    z-index: -1;
    inset: 0;
    pointer-events: none;
    opacity: 0.12;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.92' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.17'/%3E%3C/svg%3E");
}

.app-layout {
    display: flex;
    gap: 13px;
    width: 100%;
    height: 100%;
    padding: 13px;
}

.glass-panel {
    background: linear-gradient(
        145deg,
        rgba(255, 255, 255, 0.075),
        rgba(255, 255, 255, 0.025) 48%,
        rgba(151, 135, 255, 0.035)
    );
    border: 1px solid rgba(255, 255, 255, 0.105);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.075),
        0 24px 80px rgba(0, 0, 0, 0.18);
    -webkit-backdrop-filter: blur(28px) saturate(145%);
    backdrop-filter: blur(28px) saturate(145%);
}

/* SIDEBAR */

.sidebar {
    display: flex;
    flex: 0 0 268px;
    flex-direction: column;
    min-width: 0;
    padding: 17px 13px 12px;
    border-radius: 23px;
    background-color: rgba(12, 14, 25, 0.72);
}

.brand-button {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 8px 7px 19px;
    color: inherit;
    text-align: left;
    cursor: pointer;
    background: transparent;
    border: 0;
}

.brand-icon {
    display: grid;
    flex: 0 0 38px;
    width: 38px;
    height: 38px;
    place-items: center;
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 13px;
    background: linear-gradient(
        140deg,
        rgba(178, 163, 255, 0.35),
        rgba(103, 119, 255, 0.12)
    );
    box-shadow:
        inset 0 1px 1px rgba(255, 255, 255, 0.25),
        0 0 24px rgba(134, 111, 255, 0.1);
}

.brand-icon svg {
    width: 25px;
    height: 25px;
}

.brand-copy,
.account-copy,
.suggestion-copy {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.brand-name {
    font-size: 14px;
    font-weight: 650;
    letter-spacing: -0.3px;
}

.brand-caption {
    margin-top: 3px;
    color: #8889a6;
    font-size: 8px;
    font-weight: 650;
    letter-spacing: 1.8px;
}

.brand-plus {
    margin-left: auto;
    color: #b8b7d0;
    font-size: 21px;
    transition:
        transform 180ms ease,
        color 180ms ease;
}

.brand-button:hover .brand-plus {
    color: white;
    transform: rotate(90deg);
}

.new-chat-button {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 43px;
    padding: 0 11px;
    color: #f3f1ff;
    font-size: 12px;
    text-align: left;
    cursor: pointer;
    border: 1px solid rgba(190, 177, 255, 0.23);
    border-radius: 13px;
    background: linear-gradient(
        110deg,
        rgba(137, 118, 255, 0.2),
        rgba(104, 116, 255, 0.075)
    );
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
    transition:
        background 200ms ease,
        border-color 200ms ease,
        transform 200ms ease;
}

.new-chat-button:hover {
    transform: translateY(-1px);
    border-color: rgba(190, 177, 255, 0.45);
    background: linear-gradient(
        110deg,
        rgba(137, 118, 255, 0.3),
        rgba(104, 116, 255, 0.13)
    );
}

.new-chat-icon {
    font-size: 19px;
}

.shortcut {
    margin-left: auto;
    padding: 3px 5px;
    color: #a4a2c0;
    font-size: 9px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 5px;
}

.sidebar-section-label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 29px 10px 17px;
    color: #777892;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 1.5px;
}

.section-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #9e91ff;
    box-shadow: 0 0 9px rgba(158, 145, 255, 0.9);
}

.conversation-list {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.sidebar-subheading {
    margin: 0 9px 10px;
    color: #8e8fa8;
    font-size: 10px;
}

.conversation-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-width: 0;
    margin-bottom: 3px;
    padding: 10px 9px;
    color: #a9aac1;
    text-align: left;
    cursor: pointer;
    border: 1px solid transparent;
    border-radius: 11px;
    background: transparent;
    transition:
        background 180ms ease,
        border-color 180ms ease,
        color 180ms ease;
}

.conversation-item:hover,
.conversation-active {
    color: #f1efff;
    border-color: rgba(255, 255, 255, 0.07);
    background: rgba(255, 255, 255, 0.065);
}

.conversation-active {
    background: linear-gradient(
        100deg,
        rgba(143, 124, 255, 0.13),
        rgba(255, 255, 255, 0.035)
    );
}

.conversation-icon {
    flex: 0 0 16px;
    width: 16px;
    height: 16px;
}

.conversation-title {
    overflow: hidden;
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.conversation-select-button {
    display: flex;
    flex: 1;
    align-items: center;
    gap: 10px;
    min-width: 0;
    padding: 0;
    color: inherit;
    text-align: left;
    cursor: pointer;
    border: 0;
    background: transparent;
}

.delete-conversation-button {
    display: grid;
    flex: 0 0 27px;
    width: 27px;
    height: 27px;
    padding: 5px;
    place-items: center;
    color: #9991b8;
    cursor: pointer;
    border: 1px solid transparent;
    border-radius: 8px;
    background: transparent;
    opacity: 0;
    transition:
        opacity 180ms ease,
        color 180ms ease,
        background 180ms ease,
        border-color 180ms ease;
}

.delete-conversation-button svg {
    width: 15px;
    height: 15px;
}

.conversation-item:hover .delete-conversation-button,
.conversation-item:focus-within .delete-conversation-button,
.conversation-active .delete-conversation-button {
    opacity: 1;
}

.delete-conversation-button:hover {
    color: #ff929f;
    border-color: rgba(255, 120, 145, 0.2);
    background: rgba(255, 100, 130, 0.1);
}

.empty-history {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 9px;
    padding: 27px 15px;
    color: #777991;
    font-size: 10px;
    line-height: 1.7;
    text-align: center;
}

.empty-history-icon {
    color: #9990d6;
    font-size: 20px;
}

.sidebar-bottom {
    flex-shrink: 0;
    margin-top: 12px;
}

.sidebar-divider {
    height: 1px;
    margin-bottom: 12px;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.12),
        transparent
    );
}

.account-card {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 5px 11px;
}

.account-avatar {
    display: grid;
    flex: 0 0 31px;
    width: 31px;
    height: 31px;
    place-items: center;
    color: #d7d0ff;
    border: 1px solid rgba(196, 182, 255, 0.22);
    border-radius: 10px;
    background: rgba(151, 134, 255, 0.11);
}

.account-avatar svg {
    width: 17px;
    height: 17px;
}

.account-name {
    overflow: hidden;
    color: #e4e2f4;
    font-size: 10px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.account-caption {
    margin-top: 4px;
    color: #82839f;
    font-size: 9px;
}

.account-chevron {
    width: 15px;
    height: 15px;
    margin-left: auto;
    color: #777991;
}

.account-link {
    display: block;
    padding: 6px 9px;
    color: #9d9db8;
    font-size: 10px;
    text-decoration: none;
    border-radius: 7px;
    transition:
        color 180ms ease,
        background 180ms ease;
}

.account-link:hover {
    color: white;
    background: rgba(255, 255, 255, 0.06);
}

.sidebar-footer {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 12px 4px 2px;
    padding-top: 12px;
    color: #777992;
    font-size: 9px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.footer-status-dot,
.model-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #8dd9bc;
    box-shadow: 0 0 8px rgba(141, 217, 188, 0.55);
}

.footer-version {
    margin-left: auto;
    color: #8884bc;
    font-size: 8px;
    letter-spacing: 1px;
}

/* MAIN */

.main-panel {
    position: relative;
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.075);
    border-radius: 23px;
    background: linear-gradient(
        145deg,
        rgba(21, 23, 39, 0.58),
        rgba(11, 13, 23, 0.56)
    );
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.035);
    -webkit-backdrop-filter: blur(25px);
    backdrop-filter: blur(25px);
}

.topbar {
    display: flex;
    flex: 0 0 59px;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 0 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.065);
}

.topbar-left,
.topbar-right {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}

.topbar-symbol {
    color: #c2b7ff;
    font-size: 19px;
    filter: drop-shadow(0 0 8px rgba(164, 144, 255, 0.4));
}

.topbar-title {
    color: #e9e7f8;
    font-size: 11px;
    font-weight: 650;
}

.topbar-separator {
    color: #55566e;
    font-size: 12px;
}

.topbar-context {
    overflow: hidden;
    color: #8788a3;
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.model-badge {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 7px 10px;
    color: #d0cee4;
    font-size: 10px;
    border: 1px solid rgba(255, 255, 255, 0.09);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.035);
}

.model-badge svg {
    width: 12px;
    height: 12px;
    color: #8c8da8;
}

.topbar-login {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    color: #e2ddff;
    font-size: 10px;
    text-decoration: none;
    border: 1px solid rgba(173, 156, 255, 0.2);
    border-radius: 10px;
    background: rgba(141, 119, 255, 0.08);
    transition: background 180ms ease;
}

.topbar-login:hover {
    background: rgba(141, 119, 255, 0.17);
}

/* WELCOME */

.welcome-screen {
    display: flex;
    flex: 1;
    min-height: 0;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
    padding: 22px 24px 32px;
}

.welcome-content {
    width: 100%;
    max-width: 640px;
    margin: auto;
    text-align: center;
    animation: welcome-enter 750ms cubic-bezier(0.2, 0.75, 0.25, 1) both;
}

.hero-orb {
    position: relative;
    display: grid;
    width: 91px;
    height: 91px;
    margin: 0 auto 24px;
    place-items: center;
}

.orb-core {
    position: relative;
    z-index: 2;
    display: grid;
    width: 60px;
    height: 60px;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, 0.36);
    border-radius: 22px;
    background: linear-gradient(
        140deg,
        rgba(216, 207, 255, 0.24),
        rgba(104, 121, 255, 0.13)
    );
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.36),
        0 0 40px rgba(134, 117, 255, 0.21),
        0 14px 40px rgba(0, 0, 0, 0.25);
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);
    animation: orb-breathe 5s ease-in-out infinite;
}

.orb-core svg {
    width: 39px;
    height: 39px;
    filter: drop-shadow(0 0 8px rgba(219, 211, 255, 0.32));
}

.orb-ring {
    position: absolute;
    border: 1px solid rgba(182, 168, 255, 0.14);
    border-radius: 50%;
}

.orb-ring-one {
    inset: 2px;
    animation: ring-pulse 5s ease-in-out infinite;
}

.orb-ring-two {
    inset: -7px;
    border-color: rgba(164, 157, 255, 0.08);
    animation: ring-pulse 5s ease-in-out infinite reverse;
}

.orb-spark {
    position: absolute;
    z-index: 3;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #d9d2ff;
    box-shadow: 0 0 12px rgba(203, 187, 255, 0.8);
}

.orb-spark-one {
    top: 13px;
    right: 6px;
}

.orb-spark-two {
    bottom: 12px;
    left: 7px;
    width: 3px;
    height: 3px;
    animation: spark-blink 3s ease-in-out infinite;
}

.welcome-eyebrow {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 11px;
    margin-bottom: 16px;
    color: #aaa1e9;
    font-size: 9px;
    font-weight: 650;
    letter-spacing: 2.5px;
}

.eyebrow-line {
    width: 23px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(178, 162, 255, 0.7));
}

.eyebrow-line:last-child {
    transform: rotate(180deg);
}

.welcome-title {
    margin: 0;
    color: #f4f2ff;
    font-size: clamp(31px, 4.2vw, 48px);
    font-weight: 550;
    letter-spacing: -2px;
    line-height: 1.16;
}

.welcome-title span {
    display: block;
    color: #b4a8ff;
    background: linear-gradient(100deg, #e4dcff 8%, #b7a9ff 49%, #92a7ff 90%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.welcome-description {
    margin: 16px 0 29px;
    color: #9697b0;
    font-size: 12px;
    line-height: 1.9;
}

.prompt-glass {
    position: relative;
    width: 100%;
    padding: 17px 17px 12px;
    text-align: left;
    border: 1px solid rgba(211, 203, 255, 0.18);
    border-radius: 20px;
    background:
        linear-gradient(
            145deg,
            rgba(255, 255, 255, 0.075),
            rgba(255, 255, 255, 0.025) 58%
        ),
        rgba(18, 20, 34, 0.75);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.1),
        0 18px 55px rgba(0, 0, 0, 0.18),
        0 0 0 1px rgba(142, 122, 255, 0.025);
    -webkit-backdrop-filter: blur(25px) saturate(145%);
    backdrop-filter: blur(25px) saturate(145%);
    transition:
        border-color 250ms ease,
        box-shadow 250ms ease,
        background 250ms ease;
}

.prompt-glass:focus-within {
    border-color: rgba(177, 160, 255, 0.47);
    background-color: rgba(21, 22, 39, 0.84);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.13),
        0 18px 55px rgba(0, 0, 0, 0.2),
        0 0 0 3px rgba(145, 124, 255, 0.055),
        0 0 35px rgba(117, 99, 255, 0.08);
}

.prompt-textarea {
    display: block;
    width: 100%;
    min-height: 75px;
    max-height: 180px;
    padding: 0;
    resize: vertical;
    color: #f3f0ff;
    font-size: 13px;
    line-height: 1.75;
    border: 0;
    outline: none;
    background: transparent;
}

.prompt-textarea::placeholder {
    color: #74758f;
}

.prompt-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 8px;
}

.prompt-hint {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #787991;
    font-size: 9px;
}

.hint-key {
    display: grid;
    width: 17px;
    height: 17px;
    place-items: center;
    color: #aaa7c5;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 5px;
}

.hint-divider {
    margin: 0 2px;
    color: #55566e;
}

.send-button {
    display: grid;
    flex: 0 0 34px;
    width: 34px;
    height: 34px;
    place-items: center;
    color: #8888a2;
    cursor: pointer;
    border: 1px solid rgba(255, 255, 255, 0.09);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.055);
    transition:
        transform 180ms ease,
        background 180ms ease,
        box-shadow 180ms ease,
        color 180ms ease;
}

.send-button svg {
    width: 17px;
    height: 17px;
}

.send-button:disabled {
    cursor: not-allowed;
    opacity: 0.52;
}

.send-button-ready {
    color: #171327;
    border-color: rgba(228, 221, 255, 0.45);
    background: linear-gradient(135deg, #e5ddff, #a8a5ff 65%, #9ab5ff);
    box-shadow: 0 0 20px rgba(154, 137, 255, 0.19);
}

.send-button-ready:hover {
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 0 27px rgba(154, 137, 255, 0.35);
}

.suggestion-label {
    margin: 27px 0 12px;
    color: #777891;
    font-size: 8px;
    font-weight: 650;
    letter-spacing: 1.7px;
}

.suggestion-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 9px;
}

.suggestion-card {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    padding: 12px 11px;
    color: inherit;
    text-align: left;
    cursor: pointer;
    border: 1px solid rgba(255, 255, 255, 0.075);
    border-radius: 14px;
    background: linear-gradient(
        130deg,
        rgba(255, 255, 255, 0.045),
        rgba(255, 255, 255, 0.015)
    );
    transition:
        transform 200ms ease,
        border-color 200ms ease,
        background 200ms ease;
}

.suggestion-card:hover {
    transform: translateY(-2px);
    border-color: rgba(177, 162, 255, 0.24);
    background: linear-gradient(
        130deg,
        rgba(151, 130, 255, 0.1),
        rgba(255, 255, 255, 0.025)
    );
}

.suggestion-icon {
    display: grid;
    flex: 0 0 31px;
    width: 31px;
    height: 31px;
    place-items: center;
    font-size: 16px;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.035);
}

.suggestion-violet {
    color: #c5b9ff;
}

.suggestion-blue {
    color: #9bbaff;
}

.suggestion-pink {
    color: #e5a7dd;
}

.suggestion-cyan {
    color: #9de3e5;
}

.suggestion-title {
    color: #dedcf1;
    font-size: 10px;
    font-weight: 600;
}

.suggestion-description {
    margin-top: 4px;
    overflow: hidden;
    color: #81829d;
    font-size: 9px;
    line-height: 1.5;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.suggestion-arrow {
    margin-left: auto;
    color: #73748f;
    font-size: 12px;
    opacity: 0;
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}

.suggestion-card:hover .suggestion-arrow {
    opacity: 1;
    transform: translate(2px, -2px);
}

/* CHAT */

.chat-screen {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-height: 0;
}

.messages-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    scroll-behavior: smooth;
}

.messages-inner {
    width: 100%;
    max-width: 820px;
    margin: 0 auto;
    padding: 32px 32px 24px;
}

.message-row {
    display: flex;
    gap: 13px;
    margin-bottom: 29px;
    animation: message-enter 350ms cubic-bezier(0.2, 0.75, 0.25, 1) both;
    animation-delay: calc(min(var(--message-index, 0), 5) * 35ms);
}

.message-user {
    justify-content: flex-end;
}

.message-assistant {
    justify-content: flex-start;
}

.message-avatar {
    display: grid;
    flex: 0 0 30px;
    width: 30px;
    height: 30px;
    margin-top: 2px;
    place-items: center;
    color: #d0c7ff;
    border: 1px solid rgba(188, 172, 255, 0.2);
    border-radius: 11px;
    background: linear-gradient(
        140deg,
        rgba(155, 133, 255, 0.19),
        rgba(93, 115, 255, 0.08)
    );
}

.message-avatar svg {
    width: 20px;
    height: 20px;
}

.message-body {
    min-width: 0;
    max-width: calc(100% - 43px);
    padding-top: 2px;
}

.message-author {
    margin-bottom: 9px;
    color: #c6bdff;
    font-size: 10px;
    font-weight: 650;
    letter-spacing: 0.1px;
}

.message-text {
    color: #e0deee;
    font-size: 15px;
    line-height: 1.8;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

.user-message-bubble {
    max-width: 83%;
    padding: 12px 16px;
    color: #f0edff;
    font-size: 13px;
    line-height: 1.8;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
    border: 1px solid rgba(192, 178, 255, 0.15);
    border-radius: 18px 18px 5px 18px;
    background: linear-gradient(
        140deg,
        rgba(132, 114, 232, 0.19),
        rgba(106, 119, 201, 0.09)
    );
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.045);
}

.thinking-indicator {
    display: flex;
    align-items: center;
    gap: 5px;
    min-height: 18px;
    color: #a4a2c2;
    font-size: 10px;
}

.thinking-indicator > span:not(.thinking-label) {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #b9a9ff;
    animation: thinking-bounce 1.2s ease-in-out infinite;
}

.thinking-indicator > span:nth-child(2) {
    animation-delay: 150ms;
}

.thinking-indicator > span:nth-child(3) {
    animation-delay: 300ms;
}

.thinking-label {
    margin-left: 5px;
}

.chat-composer-area {
    flex-shrink: 0;
    width: 100%;
    max-width: 820px;
    margin: 0 auto;
    padding: 12px 32px 17px;
}

.chat-prompt-glass {
    padding-top: 13px;
    border-radius: 18px;
}

.chat-textarea {
    min-height: 48px;
    max-height: 150px;
}

.chat-disclaimer {
    margin: 10px 0 0;
    color: #74758f;
    font-size: 9px;
    line-height: 1.6;
    text-align: center;
}

/* SCROLLBARS */

.conversation-list,
.messages-scroll,
.welcome-screen {
    scrollbar-width: thin;
    scrollbar-color: rgba(179, 167, 255, 0.2) transparent;
}

.conversation-list::-webkit-scrollbar,
.messages-scroll::-webkit-scrollbar,
.welcome-screen::-webkit-scrollbar {
    width: 5px;
}

.conversation-list::-webkit-scrollbar-thumb,
.messages-scroll::-webkit-scrollbar-thumb,
.welcome-screen::-webkit-scrollbar-thumb {
    border-radius: 10px;
    background: rgba(179, 167, 255, 0.2);
}

/* ANIMATIONS */

@keyframes welcome-enter {
    from {
        opacity: 0;
        transform: translateY(13px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes message-enter {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes ambient-float {
    from {
        transform: translate3d(-2%, -1%, 0) scale(0.96);
    }
    to {
        transform: translate3d(4%, 3%, 0) scale(1.08);
    }
}

@keyframes orb-breathe {
    0%,
    100% {
        transform: translateY(0) rotate(-2deg);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.36),
            0 0 40px rgba(134, 117, 255, 0.21),
            0 14px 40px rgba(0, 0, 0, 0.25);
    }
    50% {
        transform: translateY(-3px) rotate(2deg);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.42),
            0 0 48px rgba(134, 117, 255, 0.32),
            0 14px 40px rgba(0, 0, 0, 0.25);
    }
}

@keyframes ring-pulse {
    0%,
    100% {
        transform: scale(0.97);
        opacity: 0.55;
    }
    50% {
        transform: scale(1.04);
        opacity: 1;
    }
}

@keyframes spark-blink {
    0%,
    100% {
        opacity: 0.45;
        transform: scale(0.8);
    }
    50% {
        opacity: 1;
        transform: scale(1.3);
    }
}

@keyframes thinking-bounce {
    0%,
    60%,
    100% {
        transform: translateY(0);
        opacity: 0.45;
    }
    30% {
        transform: translateY(-4px);
        opacity: 1;
    }
}

/* RESPONSIVE */

@media (max-width: 900px) {
    .app-layout {
        gap: 8px;
        padding: 8px;
    }

    .sidebar {
        flex-basis: 225px;
    }

    .welcome-screen {
        padding-right: 18px;
        padding-left: 18px;
    }

    .topbar {
        padding-right: 17px;
        padding-left: 17px;
    }

    .messages-inner {
        padding-right: 22px;
        padding-left: 22px;
    }

    .chat-composer-area {
        padding-right: 22px;
        padding-left: 22px;
    }
}

@media (max-width: 640px) {
    .app-shell {
        background:
            radial-gradient(
                ellipse at 50% 0%,
                rgba(92, 75, 173, 0.17),
                transparent 52%
            ),
            linear-gradient(145deg, #0b0d17, #0d0e19);
    }

    .app-layout {
        gap: 0;
        padding: 0;
    }

    .sidebar {
        display: none;
    }

    .main-panel {
        border: 0;
        border-radius: 0;
        background: rgba(12, 14, 25, 0.25);
    }

    .topbar {
        flex-basis: 54px;
        padding-right: 15px;
        padding-left: 15px;
    }

    .topbar-context,
    .topbar-separator {
        display: none;
    }

    .topbar-right {
        gap: 7px;
    }

    .model-badge {
        padding: 7px 9px;
    }

    .topbar-login {
        padding: 7px 9px;
    }

    .welcome-screen {
        align-items: flex-start;
        padding: 22px 16px 26px;
    }

    .welcome-content {
        margin: auto;
    }

    .hero-orb {
        width: 77px;
        height: 77px;
        margin-bottom: 22px;
    }

    .orb-core {
        width: 52px;
        height: 52px;
        border-radius: 19px;
    }

    .orb-core svg {
        width: 34px;
        height: 34px;
    }

    .welcome-title {
        font-size: clamp(32px, 8.5vw, 42px);
        letter-spacing: -1.5px;
    }

    .welcome-description {
        margin-top: 13px;
        margin-bottom: 24px;
        font-size: 11px;
    }

    .prompt-glass {
        padding: 13px 13px 10px;
        border-radius: 17px;
    }

    .prompt-textarea {
        min-height: 76px;
        font-size: 13px;
    }

    .prompt-hint {
        gap: 5px;
        font-size: 8px;
    }

    .hint-divider,
    .hint-divider ~ span {
        display: none;
    }

    .suggestion-grid {
        grid-template-columns: 1fr;
        gap: 7px;
    }

    .suggestion-label {
        margin-top: 22px;
    }

    .suggestion-card {
        padding: 10px;
    }

    .suggestion-arrow {
        opacity: 0.7;
    }

    .messages-inner {
        padding: 23px 15px 15px;
    }

    .message-row {
        gap: 9px;
        margin-bottom: 24px;
    }

    .message-avatar {
        flex-basis: 27px;
        width: 27px;
        height: 27px;
        border-radius: 9px;
    }

    .message-body {
        max-width: calc(100% - 36px);
    }

    .message-text,
    .user-message-bubble {
        font-size: 22px;
    }

    .user-message-bubble {
        max-width: 90%;
        padding: 10px 12px;
    }

    .chat-composer-area {
        padding: 10px 12px max(12px, env(safe-area-inset-bottom));
    }

    .chat-disclaimer {
        font-size: 8px;
    }
}

/* Respect the user's motion preferences. */

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
