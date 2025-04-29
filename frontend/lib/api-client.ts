const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8001"

async function getCsrfToken() {
  try {
    await fetch(`${API_URL.replace('/api', '')}/sanctum/csrf-cookie`, {
      credentials: 'include',
    })
  } catch (error) {
    console.error("Error getting CSRF token:", error)
  }
}

export async function login(email: string, password: string) {
  await getCsrfToken()
  
  console.log("Logging in with:", { email, api_url: API_URL })
  
  const response = await fetch(`${API_URL}/login`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Accept": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    body: JSON.stringify({ email, password }),
    credentials: "include",
  })

  if (!response.ok) {
    const error = await response.json()
    throw new Error(error.message || "Đăng nhập thất bại")
  }

  const data = await response.json()
  console.log("Login response received:", {
    status: response.status,
    hasToken: !!data.token,
    hasUser: !!data.user
  })
  
  // Save token to localStorage for API requests
  if (data.token) {
    console.log("Saving token to localStorage: [TOKEN SAVED]")
    // Store token without the Bearer prefix
    const tokenToStore = data.token.startsWith('Bearer ') ? data.token.substring(7) : data.token
    localStorage.setItem('auth_token', tokenToStore)
    
    // Lưu thông tin user vào localStorage
    if (data.user) {
      console.log("Saving user data to localStorage")
      localStorage.setItem('user_data', JSON.stringify(data.user))
      
      // Nếu user có business, lưu business_id đầu tiên làm mặc định
      if (data.user.businesses && data.user.businesses.length > 0) {
        const defaultBusiness = data.user.businesses[0]
        localStorage.setItem('business_id', defaultBusiness.id)
        console.log(`Default business set: ${defaultBusiness.name} (${defaultBusiness.id})`)
      }
    }
  } else {
    console.warn("No token received from server")
  }
  
  return data
}

export async function logout() {
  const token = localStorage.getItem('auth_token')
  await getCsrfToken()
  
  const response = await fetch(`${API_URL}/logout`, {
    method: "POST",
    headers: {
      "Accept": "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "Authorization": token ? `Bearer ${token}` : "",
    },
    credentials: "include",
  })

  // Clear all user data from localStorage
  localStorage.removeItem('auth_token')
  localStorage.removeItem('user_data')
  localStorage.removeItem('business_id')

  if (!response.ok) {
    const error = await response.json()
    throw new Error(error.message || "Đăng xuất thất bại")
  }

  return response.json()
}

export async function getUser() {
  const token = localStorage.getItem('auth_token')
  console.log("Getting user with token:", token ? "[TOKEN EXISTS]" : "no token")
  
  if (!token) {
    throw new Error("Không có token xác thực")
  }
  
  // Create AbortController for timeout
  const controller = new AbortController()
  const timeoutId = setTimeout(() => controller.abort(), 8000) // 8 seconds timeout
  
  try {
    // Make sure to include the Bearer prefix if not already present
    const authHeader = token.startsWith('Bearer ') ? token : `Bearer ${token}`
    
    console.log("Sending request to:", `${API_URL}/user`)
    console.log("With auth header:", authHeader.substring(0, 15) + "...")
    
    const response = await fetch(`${API_URL}/user`, {
      method: "GET",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "Authorization": authHeader,
      },
      credentials: "include",
      signal: controller.signal
    })
    
    clearTimeout(timeoutId)
    
    console.log("User API response status:", response.status)
    
    if (!response.ok) {
      console.error("Error getting user:", response.status, response.statusText)
      
      if (response.status === 401) {
        localStorage.removeItem('auth_token')
        throw new Error("Phiên đăng nhập hết hạn")
      }
      
      const error = await response.json().catch(() => ({ message: "Không thể kết nối đến server" }))
      throw new Error(error.message || "Không thể lấy thông tin người dùng")
    }
    
    const userData = await response.json()
    console.log("User data received:", !!userData)
    return userData
  } catch (error) {
    clearTimeout(timeoutId)
    
    if (error instanceof DOMException && error.name === "AbortError") {
      throw new Error("Yêu cầu bị hủy do timeout")
    }
    
    throw error
  }
}

// Helper functions to get user data from localStorage
export function getUserData() {
  try {
    const userData = localStorage.getItem('user_data')
    return userData ? JSON.parse(userData) : null
  } catch (error) {
    console.error('Error parsing user data from localStorage:', error)
    return null
  }
}

export function getBusinessId() {
  return localStorage.getItem('business_id')
}

export function setBusinessId(businessId: string) {
  localStorage.setItem('business_id', businessId)
}

export function getUserId() {
  const userData = getUserData()
  return userData?.id || null
}

export function getUserEmail() {
  const userData = getUserData()
  return userData?.email || null
}

export function getUserName() {
  const userData = getUserData()
  return userData?.name || null
}

export function getUserBusinesses() {
  const userData = getUserData()
  return userData?.businesses || []
} 