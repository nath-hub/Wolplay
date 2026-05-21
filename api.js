// vers le backend

const API_BASE = import.meta.env.VITE_API_BASE_URL;

export async function login(identifier, password) {
    // Correction ici : Utilisation des backticks ` ` au lieu de guillemets classiques
    const res = await fetch(`${API_BASE}/login`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json", // Bonne pratique pour préciser ce que tu attends
        },
        body: JSON.stringify({ identifier, password }),
    });

    if (!res.ok) {
        // Il est souvent utile de récupérer le message d'erreur du backend
        const errorData = await res.json().catch(() => ({}));
        throw new Error(
            errorData.message || "Identifiant ou mot de passe incorrect",
        );
    }

    return res.json();
}

/**
 * Données complètes de l'utilisateur connecté.
 * @returns {Promise<Object>}
 */
export async function getUserMainData(userId) {
    const res = await fetch(`${API_BASE}/me`, {
        method: "GET",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok)
        throw new Error("Impossible de récupérer les données utilisateur");

    return res.json();
}

/**
 * Vérification de disponibilité du pseudo (inscription).
 * @returns {Promise<boolean>}
 */
export async function isPseudoAvailable(pseudo) {
    const res = await fetch(
        `${API_BASE}/auth/check-pseudo?pseudo=${encodeURIComponent(pseudo)}`,
        {
            method: "GET",
            headers: { Accept: "application/json" },
        },
    );

    // Selon ton code PHP :
    // 200 renvoie true (dispo), 409 renvoie false (pris)
    if (res.status === 409) return false;
    if (res.ok) return res.json(); // retourne true

    return false;
}

/**
 * Vérification de disponibilité de l'email (inscription).
 * @returns {Promise<boolean>}
 */
export async function isEmailAvailable(email) {
    const res = await fetch(
        `${API_BASE}/auth/check-email?email=${encodeURIComponent(email)}`,
        {
            method: "GET",
            headers: { Accept: "application/json" },
        },
    );

    // On suit la même logique que pour le pseudo
    if (res.status === 409) return false;
    if (res.ok) return res.json(); // retourne true

    return false;
}

/**
 * Inscription. Ne connecte PAS l'utilisateur.
 * @returns {Promise<{ success: true }>}
 */
export async function register({
    pseudo,
    firstName,
    lastName,
    email,
    password,
}) {
    const res = await fetch(`${API_BASE}/register`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
        },
        body: JSON.stringify({ pseudo, firstName, lastName, email, password }),
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        // Gestion des erreurs métier selon tes specs
        if (res.status === 409) {
            if (errorData.message?.includes("pseudo"))
                throw new Error("Pseudo deja utilise");
            if (errorData.message?.includes("email"))
                throw new Error("Email deja utilise");
        }
        throw new Error(errorData.message || "Erreur lors de l'inscription");
    }

    return { success: true };
}

/**
 * Changement de mot de passe.
 * @returns {Promise<{ success: true }>}
 */
export async function updatePassword(userId, currentPassword, newPassword) {
    const res = await fetch(`${API_BASE}/auth/update-password`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
            // Assure-toi de passer le token ici si la route est protégée
        },
        body: JSON.stringify({ userId, currentPassword, newPassword }),
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        if (res.status === 401 || res.status === 403) {
            throw new Error("invalid_current_password");
        }
        throw new Error(
            errorData.message || "Erreur lors du changement de mot de passe",
        );
    }

    return res.json(); // Doit retourner { success: true }
}

/**
 * Changement d'email — envoie un lien de vérification.
 * @returns {Promise<{ pending: true }>}
 */
export async function updateEmail(newEmail, password) {
    const res = await fetch(`${API_BASE}/auth/update-email`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
        },
        body: JSON.stringify({ newEmail, password }),
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));

        if (res.status === 409) throw new Error("email_already_used");
        if (res.status === 401) throw new Error("invalid_password");

        throw new Error(
            errorData.message || "Erreur lors de la mise à jour de l'email",
        );
    }

    return res.json(); // Doit retourner { pending: true }
}

/**
 * Profil public par pseudo.
 * @returns {Promise<Object|null>}
 */
export async function fetchPublicProfileByPseudo(pseudo) {
    const res = await fetch(`${API_BASE}/public/profile/${pseudo}`, {
        method: "GET",
        headers: {
            Accept: "application/json",
        },
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(
            errorData.message || "Erreur lors de la récupération du profil",
        );
    }

    const data = await res.json();

    // 👉 Si backend retourne null
    return data || null;
}

/**
 * Récupérer un profil complet par ID
 * @returns {Promise<Object|null>}
 */
export async function fetchUserById(userId) {
    const res = await fetch(`${API_BASE}/users/${userId}`, {
        method: "GET",
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(
            errorData.message ||
                "Erreur lors de la récupération de l'utilisateur",
        );
    }

    const data = await res.json();

    // backend retourne null si inexistant
    return data || null;
}

function getToken() {
    try {
        const stored = localStorage.getItem("wolplay.currentUser");
        return JSON.parse(stored)?.authSession?.accessToken ?? null;
    } catch {
        return null;
    }
}

/**
 * Feed vidéos
 * @returns {Promise<Array>}
 */
export async function fetchVideoFeed({
    context = "global",
    creatorId = null,
    limit = 20,
    offset = 0,
} = {}) {
    const params = new URLSearchParams();

    if (context) params.append("context", context);
    if (creatorId) params.append("creatorId", creatorId);
    if (limit) params.append("limit", limit);
    if (offset) params.append("offset", offset);

    const res = await fetch(`${API_BASE}/videos/feed?${params.toString()}`, {
        method: "GET",
        headers: {
            Accept: "application/json",
        },
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(
            errorData.message || "Erreur lors du chargement du feed",
        );
    }

    return await res.json();
}

/**
 * Récupérer la prochaine vidéo
 * @returns {Promise<Object|null>}
 */
export async function fetchNextVideo(
    currentVideoId,
    { context = "global", creatorId = null } = {},
) {
    if (!currentVideoId) {
        throw new Error("currentVideoId is required");
    }

    const params = new URLSearchParams();

    if (context) params.append("context", context);
    if (creatorId) params.append("creatorId", creatorId);

    const res = await fetch(
        `${API_BASE}/videos/next/${currentVideoId}?${params.toString()}`,
        {
            method: "GET",
            headers: {
                Accept: "application/json",
            },
        },
    );

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));

        if (res.status === 404) {
            throw new Error("video_not_found");
        }

        throw new Error(
            errorData.message ||
                "Erreur lors du chargement de la prochaine vidéo",
        );
    }

    const data = await res.json();

    return data || null;
}

/**
 * Vidéo mise en avant (featured)
 * @returns {Promise<Object|null>}
 */
export async function fetchFeaturedVideo() {
    const res = await fetch(`${API_BASE}/featured/videos`, {
        method: "GET",
        headers: {
            Accept: "application/json",
        },
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(
            errorData.message ||
                "Erreur lors du chargement de la vidéo mise en avant",
        );
    }

    const data = await res.json();
    return data || null;
}

/**
 * Vidéos du showcase homepage
 * @returns {Promise<Array>}
 */
export async function fetchHomeShowcase() {
    const res = await fetch(`${API_BASE}/home/showcase`, {
        method: "GET",
        headers: {
            Accept: "application/json",
        },
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(
            errorData.message || "Erreur lors du chargement du showcase",
        );
    }

    return await res.json(); // toujours un tableau
}

/**
 * Vidéos collection homepage
 * @returns {Promise<Array>}
 */
export async function fetchHomeCollection() {
    const res = await fetch(`${API_BASE}/home/collection`, {
        method: "GET",
        headers: {
            Accept: "application/json",
        },
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(
            errorData.message || "Erreur lors du chargement des collections",
        );
    }

    return await res.json();
}

/**
 * Créateurs homepage
 * @returns {Promise<Array>}
 */
export async function fetchHomeCreators() {
    const res = await fetch(`${API_BASE}/home/creators`, {
        method: "GET",
        headers: {
            Accept: "application/json",
        },
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(
            errorData.message || "Erreur lors du chargement des créateurs",
        );
    }

    return await res.json();
}

/**
 * Wolplay vidéos
 * @returns {Promise<Array>}
 */
export async function fetchWolplayVideos({
    discipline = null,
    format = null,
    limit = 20,
    offset = 0,
} = {}) {
    const params = new URLSearchParams();

    if (discipline) params.append("discipline", discipline);
    if (format) params.append("format", format);
    if (limit) params.append("limit", limit);
    if (offset) params.append("offset", offset);

    const res = await fetch(
        `${API_BASE}/wolplay/creators?${params.toString()}`,
        {
            method: "GET",
            headers: {
                Accept: "application/json",
            },
        },
    );

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(
            errorData.message || "Erreur chargement Wolplay vidéos",
        );
    }

    return await res.json();
}

/**
 * Wolplay spotlight
 * @returns {Promise<Array>}
 */
export async function fetchWolplaySpotlight() {
    const res = await fetch(`${API_BASE}/wolplay/spotlight`, {
        method: "GET",
        headers: {
            Accept: "application/json",
        },
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(errorData.message || "Erreur chargement spotlight");
    }

    return await res.json();
}

/**
 * Tutoriels
 */
export async function fetchTutorialVideos({
    discipline = null,
    limit = 20,
    offset = 0,
} = {}) {
    const params = new URLSearchParams();

    if (discipline) params.append("discipline", discipline);
    if (limit) params.append("limit", limit);
    if (offset) params.append("offset", offset);

    const res = await fetch(`${API_BASE}/videos/tutorial?${params}`, {
        headers: { Accept: "application/json" },
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur chargement tutoriels");
    }

    return await res.json();
}

/**
 * Spotlight tutoriels
 */
export async function fetchTutorialSpotlight() {
    const res = await fetch(`${API_BASE}/tutorials/spotlight`, {
        headers: { Accept: "application/json" },
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur spotlight tutoriels");
    }

    return await res.json();
}


/**
 * Spotlights collections (pro + premium)
 */
export async function fetchCollectionSpotlights() {
    const res = await fetch(`${API_BASE}/collection/spotlights`, {
        headers: { Accept: "application/json" },
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur spotlights collections");
    }

    return await res.json();
}

/**
 * Détail d'une vidéo
 */
export async function fetchVideoById(videoId) {
    const res = await fetch(`${API_BASE}/videos/${videoId}`, {
        headers: { Accept: "application/json" },
    });

    if (!res.ok) {
        if (res.status === 404) throw new Error("video_not_found");

        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur chargement vidéo");
    }

    return await res.json();
}

/**
 * Vidéos d’un créateur
 */
export async function fetchCreatorVideos(
    creatorId,
    { category = null, limit = 20, offset = 0 } = {},
) {
    const params = new URLSearchParams();

    if (category) params.append("category", category);
    if (limit) params.append("limit", limit);
    if (offset) params.append("offset", offset);

    const res = await fetch(
        `${API_BASE}/videos/creator/${creatorId}?${params}`,
        {
            headers: { Accept: "application/json" },
        },
    );

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur vidéos créateur");
    }

    return await res.json();
}

//@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

/**
 * Suivre un créateur
 * @returns {Promise<{ following: true }>}
 */
export async function followCreator(creatorId) {
    const res = await fetch(`${API_BASE}/creators/${creatorId}/follow`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur follow creator");
    }

    return await res.json(); // { following: true }
}

/**
 * Se désabonner d’un créateur
 * @returns {Promise<{ following: false }>}
 */
export async function unfollowCreator(creatorId) {
    const res = await fetch(`${API_BASE}/creators/${creatorId}/follow`, {
        method: "DELETE",
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur unfollow creator");
    }

    return await res.json(); // { following: false }
}

/**
 * Créateurs recommandés
 * @returns {Promise<CreatorCard[]>}
 */
export async function fetchRecommendedCreators({
    userId,
    creatorId,
    limit = 6,
    excludeIds = [],
    token,
} = {}) {
    const params = new URLSearchParams();
    const effectiveUserId = userId ?? creatorId;

    if (effectiveUserId) params.append("userId", effectiveUserId);
    if (limit) params.append("limit", limit);

    if (excludeIds.length) {
        excludeIds.forEach((id) => params.append("excludeIds[]", id));
    }

    const res = await fetch(`${API_BASE}/creators/recommended?${params}`, {
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${token ?? getToken()}`,
        },
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur creators recommandés");
    }

    return await res.json();
}

/**
 * Vérifie si l'utilisateur suit un créateur
 * @returns {Promise<{ following: boolean }>}
 */
export async function fetchFollowStatus(creatorId) {
    const res = await fetch(`${API_BASE}/creators/${creatorId}/follow`, {
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur fetch follow status");
    }

    return await res.json(); // { following: true | false }
}

/**
 * Liste des créateurs suivis par un user
 * @returns {Promise<CreatorCard[]>}
 */
export async function fetchFollowing(userId) {
    const res = await fetch(`${API_BASE}/users/${userId}/following`, {
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`, // optionnel si public
        },
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur fetch following");
    }

    return await res.json();
}

/**
 * Liste des followers d’un user
 * @returns {Promise<CreatorCard[]>}
 */
export async function fetchFollowers(userId) {
    const res = await fetch(`${API_BASE}/users/${userId}/followers`, {
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`, // optionnel
        },
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur fetch followers");
    }

    return await res.json();
}

/**
 * Vidéos du créateur (pinned)
 */
export async function fetchPinnedVideos(userId) {
    const res = await fetch(
        `${API_BASE}/videos/pinned?userId=${encodeURIComponent(userId)}`,
        {
            headers: {
                Accept: "application/json",
                Authorization: `Bearer ${getToken()}`, // optionnel
            },
        },
    );

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur fetch pinned videos");
    }

    return await res.json();
}

/**
 * Ajouter une vidéo
 */
export async function addPinnedVideo(userId, data) {
    const res = await fetch(`${API_BASE}/videos/pinned`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
        body: JSON.stringify({ userId, ...data }),
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur ajout vidéo");
    }

    return await res.json();
}

/**
 * Supprimer une vidéo
 */
export async function deletePinnedVideo(userId, videoId) {
    const res = await fetch(`${API_BASE}/videos/pinned/${videoId}`, {
        method: "DELETE",
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok && res.status !== 204) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur suppression vidéo");
    }

    return true;
}

/**
 * IDs des vidéos mises en avant
 * @returns {Promise<string[]>}
 */
export async function fetchFeaturedVideoIds(userId) {
    const res = await fetch(`${API_BASE}/videos/featured-ids?userId=${encodeURIComponent(userId)}`, {
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur fetch featured");
    }

    return await res.json();
}

/**
 * Mettre à jour les vidéos mises en avant
 */
export async function updateFeaturedVideoIds(userId, ids) {
    const res = await fetch(`${API_BASE}/videos/featured-ids?userId=${encodeURIComponent(userId)}`, {
        method: "PUT",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
        body: JSON.stringify({ ids }),
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur update featured");
    }

    return await res.json(); // retourne ids
}

/**
 * Ajouter un événement agenda
 * @returns {Promise<AgendaItem>}
 */
export async function addAgendaEvent(profileId, data) {
    const res = await fetch(`${API_BASE}/creators/${profileId}/agenda`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
        body: JSON.stringify(data),
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur ajout agenda");
    }

    return await res.json();
}

/**
 * Modifier un événement agenda
 * @returns {Promise<AgendaItem>}
 */
export async function updateAgendaEvent(profileId, eventId, data) {
    const res = await fetch(
        `${API_BASE}/creators/${profileId}/agenda/${eventId}`,
        {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                Authorization: `Bearer ${getToken()}`,
            },
            body: JSON.stringify(data),
        },
    );

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur update agenda");
    }

    return await res.json();
}

/**
 * Supprimer un événement agenda
 */
export async function deleteAgendaEvent(profileId, eventId) {
    const res = await fetch(
        `${API_BASE}/creators/${profileId}/agenda/${eventId}`,
        {
            method: "DELETE",
            headers: {
                Accept: "application/json",
                Authorization: `Bearer ${getToken()}`,
            },
        },
    );

    if (!res.ok && res.status !== 204) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Erreur suppression agenda");
    }

    return true;
}

// fetchDashboardFeed
// GET /dashboard/feed
// @returns { items, nextOffset, hasMore }

export async function fetchDashboardFeed({ offset = 0, limit = 10 } = {}) {
    const res = await fetch(
        `${API_BASE}/dashboard/feed?offset=${offset}&limit=${limit}`,
        {
            method: "GET",
            headers: {
                Accept: "application/json",
                Authorization: `Bearer ${getToken()}`,
            },
        },
    );

    if (!res.ok) {
        throw new Error("Erreur lors du chargement du feed");
    }

    return res.json();
}

// createDashboardPost
// POST /dashboard/posts

export async function createDashboardPost(data) {
    const res = await fetch(`${API_BASE}/dashboard/posts`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
        body: JSON.stringify(data),
    });

    if (!res.ok) {
        const error = await res.json().catch(() => ({}));
        throw new Error(error.message || "Erreur création post");
    }

    return res.json();
}

// deleteDashboardPost
// DELETE /dashboard/posts/{postId}

export async function deleteDashboardPost(postId) {
    const res = await fetch(`${API_BASE}/dashboard/posts/${postId}`, {
        method: "DELETE",
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) {
        throw new Error("Erreur suppression post");
    }

    return true;
}

// updateWipPost
// PATCH /dashboard/wip

export async function updateWipPost(data) {
    const res = await fetch(`${API_BASE}/dashboard/wip`, {
        method: "PATCH",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
        body: JSON.stringify(data),
    });

    if (!res.ok) {
        const error = await res.json().catch(() => ({}));
        throw new Error(error.message || "Erreur mise à jour WIP");
    }

    return res.json();
}

// toggleWipPin
// POST /dashboard/wip/{postId}/pin

export async function toggleWipPin(postId) {
    const res = await fetch(`${API_BASE}/dashboard/wip/${postId}/pin`, {
        method: "POST",
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) {
        throw new Error("Erreur pin/unpin WIP");
    }

    return res.json(); // { is_pinned: boolean }
}

// fetchCurrentPlan
// GET /subscription
// @returns { plan, expiresAt, status }

export async function fetchCurrentPlan() {
    const res = await fetch(`${API_BASE}/subscription`, {
        method: "GET",
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) {
        throw new Error("Erreur récupération abonnement");
    }

    return res.json();
}

// upgradeToPremium
// POST /subscription/upgrade
// @returns { success: true, plan: "premium" }

export async function upgradeToPremium() {
    const res = await fetch(`${API_BASE}/subscription/upgrade`, {
        method: "POST",
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) {
        const error = await res.json().catch(() => ({}));

        if (res.status === 422) {
            throw new Error(error.message || "Action impossible");
        }

        throw new Error("Erreur upgrade premium");
    }

    return res.json();
}

// cancelPremium
// DELETE /subscription/premium
// @returns { success: true, plan: "free" }

export async function cancelPremium() {
    const res = await fetch(`${API_BASE}/subscription/cancel`, {
        method: "POST",
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) {
        const error = await res.json().catch(() => ({}));

        if (res.status === 422) {
            throw new Error(error.message || "Aucun abonnement à annuler");
        }

        throw new Error("Erreur annulation abonnement");
    }

    return res.json();
}

/**
 * Récupérer les événements d'agenda d'un créateur
 * @param {string} profileId
 * @returns {Promise<Array>}
 */
export async function fetchAgendaEvents(profileId) {
    const res = await fetch(`${API_BASE}/creators/${profileId}/agenda`, {
        method: "GET",
        headers: {
            Accept: "application/json",
        },
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));

        if (res.status === 404) throw new Error("creator_not_found");

        throw new Error(
            errorData.message || "Erreur lors de la récupération de l'agenda",
        );
    }

    return res.json(); // retourne un tableau d'événements
}

export async function fetchCreatorsList({
    discipline = "",
    search = "",
    sort = "recent",
    limit = 30,
    offset = 0,
} = {}) {
    const params = new URLSearchParams();

    if (discipline) params.append("discipline", discipline);
    if (search) params.append("search", search);
    if (sort) params.append("sort", sort);
    if (limit) params.append("limit", limit);
    if (offset) params.append("offset", offset);

    const res = await fetch(`${API_BASE}/creators?${params.toString()}`, {
        method: "GET",
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(
            errorData.message || "Erreur lors du chargement des créateurs",
        );
    }

    return await res.json();
}

export async function fetchCollectionVideos({ limit = 20, offset = 0 } = {}) {
    const params = new URLSearchParams();

    if (limit) params.append("limit", limit);
    if (offset) params.append("offset", offset);

    const res = await fetch(
        `${API_BASE}/videos/collection?${params.toString()}`,
        {
            method: "GET",
            headers: {
                Accept: "application/json",
            },
        },
    );

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(
            errorData.message ||
                "Erreur lors du chargement des vidéos collections",
        );
    }

    return await res.json();
}

/**
 * Met à jour le profil public d'un utilisateur
 * @param {string} pseudo - Le pseudo de l'utilisateur (utilisé dans l'URL)
 * @param {Object} elements - Les données à mettre à jour (name, avatar, bio, etc.)
 */
export const updatePublicProfile = async (pseudo, elements) => {
    try {
        const response = await fetch(`${API_BASE}/profile/public/${pseudo}`, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                Authorization: `Bearer ${getToken()}`,
            },
            body: JSON.stringify(elements),
        });

        const data = await response.json();

        if (!response.ok) {
            // Gestion des erreurs de validation (422) ou autres
            throw new Error(data.message || "update_failed");
        }

        return data; // Retourne l'objet utilisateur complet formaté par ton contrôleur
    } catch (error) {
        console.error("Erreur lors de la mise à jour du profil:", error);
        throw error;
    }
};


export const updateEtabliItem = async (etabliId, elements) => {
    try {
        const response = await fetch(`${API_BASE}/etabli/items/${etabliId}`, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                Authorization: `Bearer ${getToken()}`,
            },
            body: JSON.stringify(elements),
        });

        const data = await response.json();

        if (!response.ok) {
            // Gestion des erreurs de validation (422) ou autres
            throw new Error(data.message || "update_failed");
        }

        return data; // Retourne l'objet utilisateur complet formaté par ton contrôleur
    } catch (error) {
        console.error("Erreur lors de la mise à jour du profil:", error);
        throw error;
    }
};


/**
 * Récupère le flux de l'Atelier avec pagination
 * @param {number} offset - Point de départ pour la pagination
 * @param {number} limit - Nombre d'éléments à récupérer
 */
export const fetchAtelierFeed = async (offset = 0, limit = 10) => {
    try {
        const response = await fetch(`${API_BASE}/atelier/feed?offset=${offset}&limit=${limit}`, {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                // Authorization si le flux est privé ou personnalisé
                "Authorization": `Bearer ${getToken()}`,
            },
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || "failed_to_fetch_feed");
        }

        return await response.json();
        // Retourne : { items: [], nextOffset: 10, hasMore: true }
    } catch (error) {
        console.error("Erreur fetchAtelierFeed:", error);
        throw error;
    }
};


export const fetchEtabliItems = async (creatorId, offset = 0, limit = 50) => {
    try {
        // Construction de l'URL avec les query params
        const url = new URL(`${API_BASE}/etabli/items`);
        url.searchParams.append('creatorId', creatorId);
        url.searchParams.append('offset', offset);
        url.searchParams.append('limit', limit);

        const response = await fetch(url, {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "Authorization": `Bearer ${getToken()}`,
            },
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || "failed_to_fetch_items");
        }

        return await response.json();
    } catch (error) {
        console.error("Erreur fetchEtabliItems:", error);
        throw error;
    }
};


export const createEtabliItem = async (itemData) => {
    try {
        const response = await fetch(`${API_BASE}/etabli/items`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "Authorization": `Bearer ${getToken()}`,
            },
            // itemData doit contenir : title, description, images, status, isPinned
            body: JSON.stringify(itemData),
        });

        const data = await response.json();

        if (!response.ok) {
            // Laravel renvoie les erreurs de validation ici
            throw new Error(data.message || "failed_to_create_item");
        }

        return data; // Retourne l'item formaté par formatEtabliResponse
    } catch (error) {
        console.error("Erreur createEtabliItem:", error);
        throw error;
    }
};


export const createAtelierPost = async (postData) => {
    try {
        const response = await fetch(`${API_BASE}/atelier/posts`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "Authorization": `Bearer ${getToken()}`,
            },
            // postData doit contenir : { text: "...", images: ["url1", "url2"] }
            body: JSON.stringify(postData),
        });

        const data = await response.json();

        if (!response.ok) {
            // Gestion des erreurs de validation (ex: texte trop long ou manquant)
            throw new Error(data.message || "failed_to_create_post");
        }

        return data; // Retourne le post formaté par formatPostResponse
    } catch (error) {
        console.error("Erreur createAtelierPost:", error);
        throw error;
    }
};

export const deleteAtelierPost = async (postId) => {
    if (!postId) {
        throw new Error("postId is required");
    }

    const response = await fetch(`${API_BASE}/atelier/posts/${postId}`, {
        method: "DELETE",
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || "failed_to_delete_post");
    }

    return null;
};



export const deleteEtabliItem = async (etabliId) => {
    if (!etabliId) {
        throw new Error("etabliId is required");
    }

    const response = await fetch(`${API_BASE}/etabli/items/${etabliId}`, {
        method: "DELETE",
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || "failed_to_delete_etabli");
    }

    return null;
};



export const renewImageUrl = async (params) => {
    if (!params.entityType || !params.entityId || params.imageIndex === undefined || !params.newUrl) {
        throw new Error("entityType, entityId, imageIndex, and newUrl are required");
    }

    const response = await fetch(`${API_BASE}/images/renew`, {
        method: "PATCH",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
        body: JSON.stringify(params),
    });

    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.message || "failed_to_renew_image");
    }

    return data;
};

export const updateEtabliOrder = async (creatorId, orderedIds, pinnedId = null) => {
    if (!creatorId || !orderedIds || !Array.isArray(orderedIds)) {
        throw new Error("creatorId and orderedIds array are required");
    }

    const response = await fetch(`${API_BASE}/etabli/order`, {
        method: "PUT",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${getToken()}`,
        },
        body: JSON.stringify({
            creatorId,
            orderedIds,
            pinnedId,
        }),
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || "failed_to_update_etabli_order");
    }

    return null;
};
