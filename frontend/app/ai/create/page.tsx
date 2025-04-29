"use client"

import { useState, useEffect } from "react"
import { useRouter } from "next/navigation"
import { AppShell } from "@/components/layout/app-shell"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { Checkbox } from "@/components/ui/checkbox"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { ArrowLeftIcon, Loader2Icon } from "lucide-react"
import { useToast } from "@/components/ui/use-toast"
import Link from "next/link"
import { getBusinessId } from "@/lib/api-client"

export default function CreateAIAgentPage() {
  const [formData, setFormData] = useState({
    name: "New Agent",
    description: "A helpful AI assistant",
    personality: "helpful",
    system_prompt: "You are a helpful and friendly AI assistant. You provide accurate and concise information to users' questions.",
    access_role: "member",
    model: "gpt-3.5-turbo",
    capabilities: ["image_upload"] as string[],
  })
  
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const [businessId, setBusinessId] = useState<string | null>(null)
  const [avatarFile, setAvatarFile] = useState<File | null>(null)
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  
  const router = useRouter()
  const { toast } = useToast()

  useEffect(() => {
    // Check for login and get business ID
    const token = localStorage.getItem('auth_token')
    setIsLoggedIn(!!token)
    
    const currentBusinessId = getBusinessId()
    if (currentBusinessId) {
      setBusinessId(currentBusinessId)
    }
    
    // Debug environment variables
    console.log("Environment variables:", {
      NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL,
      NODE_ENV: process.env.NODE_ENV,
    })
  }, [])

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target
    setFormData(prev => ({ ...prev, [name]: value }))
  }

  const handleSelectChange = (name: string, value: string) => {
    setFormData(prev => ({ ...prev, [name]: value }))
  }

  const handleCapabilityChange = (capability: string, checked: boolean) => {
    setFormData(prev => ({
      ...prev,
      capabilities: checked 
        ? [...prev.capabilities, capability]
        : prev.capabilities.filter(c => c !== capability)
    }))
  }

  const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0]
      setAvatarFile(file)
      
      // Create preview
      const reader = new FileReader()
      reader.onloadend = () => {
        setAvatarPreview(reader.result as string)
      }
      reader.readAsDataURL(file)
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!businessId) {
      toast({
        title: "Error",
        description: "Please select a business first",
        variant: "destructive"
      })
      return
    }
    
    setIsSubmitting(true)
    
    try {
      const token = localStorage.getItem('auth_token')
      
      // Đảm bảo lấy đúng API URL từ biến môi trường
      const apiUrl = process.env.NEXT_PUBLIC_API_URL
      if (!apiUrl) {
        console.error("NEXT_PUBLIC_API_URL is not defined")
        toast({
          title: "Configuration Error",
          description: "API URL is not configured correctly",
          variant: "destructive"
        })
        return
      }
      
      console.log("Using API URL:", apiUrl)
      
      // Lấy CSRF token trước khi gửi POST request
      try {
        console.log("Getting CSRF token")
        const fullDomain = apiUrl.replace('/api', '')
        console.log("CSRF request URL:", `${fullDomain}/sanctum/csrf-cookie`)
        await fetch(`${fullDomain}/sanctum/csrf-cookie`, {
          credentials: 'include',
          mode: 'cors'
        });
      } catch (error) {
        console.warn("Could not get CSRF token:", error)
      }
      
      // Create FormData to handle file upload
      const formDataToSend = new FormData()
      formDataToSend.append('name', formData.name)
      formDataToSend.append('description', formData.description || '')
      formDataToSend.append('personality', formData.personality)
      formDataToSend.append('system_prompt', formData.system_prompt)
      formDataToSend.append('access_role', formData.access_role)
      formDataToSend.append('model', formData.model)
      formDataToSend.append('business_id', businessId)
      
      // Thay thế bằng cách gửi mảng đúng cách
      formData.capabilities.forEach(capability => {
        formDataToSend.append('capabilities[]', capability);
      });
      
      // Add avatar if selected
      if (avatarFile) {
        formDataToSend.append('avatar', avatarFile)
      }
      
      // Debug FormData
      console.log("Form data being sent:");
      for (const pair of formDataToSend.entries()) {
        if (pair[0] === 'avatar' && pair[1] instanceof File) {
          console.log(pair[0], "File:", pair[1].name, pair[1].type, pair[1].size);
        } else {
          console.log(pair[0], pair[1]);
        }
      }
      
      const fullUrl = `${apiUrl}/ai-agents`
      console.log("Submitting to:", fullUrl)
      
      const response = await fetch(fullUrl, {
        method: 'POST',
        headers: token ? {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          } : {},
        body: formDataToSend,
        mode: 'cors',
        credentials: 'include'
      })
      
      console.log("Response status:", response.status);
      console.log("Response headers:", Object.fromEntries([...response.headers]));
      
      if (response.status === 302) {
        const redirectUrl = response.headers.get('Location');
        console.error("Redirect detected to:", redirectUrl);
        
        // Lấy nội dung response để debug
        try {
          const responseText = await response.text();
          console.error("Response text:", responseText);
        } catch (e) {
          console.error("Could not read response text:", e);
        }
      }
      
      if (!response.ok) {
        const errorText = await response.text()
        console.error("Error response text:", errorText);
        
        let errorData: any;
        try {
          errorData = JSON.parse(errorText)
          console.error("Error response:", errorData)
          
          // Handle Laravel validation errors
          if (response.status === 422 && errorData.errors) {
            console.error("Validation errors:", errorData.errors)
            
            // Format validation errors for display
            let errorMsg = "Validation failed:";
            Object.keys(errorData.errors).forEach(field => {
              const fieldErrors = errorData.errors[field];
              if (Array.isArray(fieldErrors)) {
                errorMsg += `\n- ${field}: ${fieldErrors.join(', ')}`;
              }
            });
            
            toast({
              title: "Validation Error",
              description: errorMsg,
              variant: "destructive"
            });
            
            return; // Prevent the default error handling
          }
        } catch (e) {
          console.error("Failed to parse error response:", errorText)
          throw new Error('Failed to create AI agent: Server error')
        }
        throw new Error(errorData?.message || 'Failed to create AI agent')
      }
      
      const data = await response.json()
      
      toast({
        title: "Success",
        description: "AI Agent created successfully",
      })
      
      // Redirect to AI agents list using Next.js router
      setTimeout(() => {
        router.push('/ai')
      }, 500)
    } catch (error) {
      console.error('Error creating AI agent:', error)
      toast({
        title: "Error",
        description: error instanceof Error ? error.message : "Failed to create AI agent",
        variant: "destructive"
      })
    } finally {
      setIsSubmitting(false)
    }
  }

  // Generate system prompt based on personality, if no custom prompt is provided
  useEffect(() => {
    if (!formData.system_prompt && formData.personality) {
      let prompt = ""
      
      switch (formData.personality) {
        case "helpful":
          prompt = "You are a helpful and friendly AI assistant. You provide accurate and concise information to users' questions."
          break
        case "professional":
          prompt = "You are a professional AI assistant. You provide detailed and accurate information in a formal tone."
          break
        case "creative":
          prompt = "You are a creative AI assistant. You think outside the box and provide innovative solutions to problems."
          break
        case "friendly":
          prompt = "You are a friendly and casual AI assistant. You use a conversational tone and make users feel comfortable."
          break
        default:
          prompt = "You are a helpful AI assistant."
      }
      
      setFormData(prev => ({ ...prev, system_prompt: prompt }))
    }
  }, [formData.personality])

  if (!isLoggedIn) {
    return (
      <AppShell>
        <div className="container mx-auto py-6">
          <div className="p-8 bg-yellow-50 border border-yellow-300 rounded-md">
            <h3 className="text-lg font-semibold mb-2">Bạn cần đăng nhập trước</h3>
            <p>Vui lòng <a href="/login" className="text-blue-600 hover:underline">đăng nhập</a> để sử dụng tính năng này.</p>
          </div>
        </div>
      </AppShell>
    )
  }

  if (!businessId) {
    return (
      <AppShell>
        <div className="container mx-auto py-6">
          <div className="p-8 bg-yellow-50 border border-yellow-300 rounded-md">
            <h3 className="text-lg font-semibold mb-2">Chọn doanh nghiệp</h3>
            <p>Vui lòng chọn một doanh nghiệp để tạo AI Agent.</p>
          </div>
        </div>
      </AppShell>
    )
  }

  return (
    <AppShell>
      <div className="container mx-auto py-6 space-y-6">
        <div className="flex items-center mb-6">
          <Button variant="ghost" className="mr-4" asChild>
            <Link href="/ai">
              <ArrowLeftIcon className="h-4 w-4 mr-2" />
              Back
            </Link>
          </Button>
          <div>
            <h1 className="text-2xl font-bold">Create new AI Agent</h1>
            <p className="text-muted-foreground">Create a custom AI agent for your business</p>
          </div>
        </div>

        {/* Debug info */}
        <div className="p-4 mb-4 bg-slate-100 rounded-md text-xs">
          <p>API URL: {process.env.NEXT_PUBLIC_API_URL || 'Not set'}</p>
          <p>Business ID: {businessId || 'Not set'}</p>
          <p>Login status: {isLoggedIn ? 'Logged in' : 'Not logged in'}</p>
          <p>Token: {isLoggedIn ? 'Present' : 'Missing'}</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-8 max-w-2xl mx-auto">
          <Card>
            <CardHeader>
              <CardTitle>Basic Information</CardTitle>
              <CardDescription>Provide the basic details about your AI agent</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">Name <span className="text-red-500">*</span></Label>
                <Input 
                  id="name" 
                  name="name" 
                  value={formData.name} 
                  onChange={handleChange} 
                  placeholder="e.g. Sales Assistant"
                  required
                />
                <p className="text-sm text-muted-foreground">A name for your AI agent</p>
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="description">Description</Label>
                <Textarea 
                  id="description" 
                  name="description" 
                  value={formData.description} 
                  onChange={handleChange} 
                  placeholder="Describe what this AI agent does..."
                  rows={3}
                />
                <p className="text-sm text-muted-foreground">A brief description of your AI agent's purpose</p>
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="avatar">Avatar</Label>
                <div className="flex items-center space-x-4">
                  <Avatar className="h-16 w-16">
                    <AvatarImage src={avatarPreview || ""} />
                    <AvatarFallback>{formData.name.substring(0, 2).toUpperCase()}</AvatarFallback>
                  </Avatar>
                  <Input 
                    id="avatar" 
                    name="avatar" 
                    type="file" 
                    accept="image/*"
                    onChange={handleAvatarChange}
                  />
                </div>
                <p className="text-sm text-muted-foreground">Upload an avatar image for your AI agent (optional)</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>AI Configuration</CardTitle>
              <CardDescription>Configure how your AI agent behaves and responds</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="personality">Personality</Label>
                <Select 
                  value={formData.personality} 
                  onValueChange={(value) => handleSelectChange('personality', value)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select a personality" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="helpful">Helpful</SelectItem>
                    <SelectItem value="professional">Professional</SelectItem>
                    <SelectItem value="creative">Creative</SelectItem>
                    <SelectItem value="friendly">Friendly</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-sm text-muted-foreground">The general personality of your AI agent</p>
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="system_prompt">System Prompt</Label>
                <Textarea 
                  id="system_prompt" 
                  name="system_prompt" 
                  value={formData.system_prompt} 
                  onChange={handleChange} 
                  placeholder="Instructions for how the AI should behave..."
                  rows={4}
                />
                <p className="text-sm text-muted-foreground">Specific instructions that define your AI agent's behavior</p>
              </div>
              
              <div className="space-y-2">
                <Label>Capabilities</Label>
                <div className="grid grid-cols-2 gap-2">
                  <div className="flex items-center space-x-2">
                    <Checkbox 
                      id="voice_capability" 
                      checked={formData.capabilities.includes('voice')}
                      onCheckedChange={(checked) => handleCapabilityChange('voice', checked as boolean)}
                    />
                    <Label htmlFor="voice_capability" className="font-normal">Voice</Label>
                  </div>
                  <div className="flex items-center space-x-2">
                    <Checkbox 
                      id="image_upload_capability" 
                      checked={formData.capabilities.includes('image_upload')}
                      onCheckedChange={(checked) => handleCapabilityChange('image_upload', checked as boolean)}
                    />
                    <Label htmlFor="image_upload_capability" className="font-normal">Image Upload</Label>
                  </div>
                </div>
                <p className="text-sm text-muted-foreground">Special capabilities your AI agent should have</p>
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="model">AI Model</Label>
                <Select 
                  value={formData.model} 
                  onValueChange={(value) => handleSelectChange('model', value)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select a model" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="gpt-3.5-turbo">GPT-3.5 Turbo</SelectItem>
                    <SelectItem value="gpt-4">GPT-4</SelectItem>
                    <SelectItem value="claude-3">Claude 3</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-sm text-muted-foreground">The underlying AI model to use</p>
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="access_role">Access Role</Label>
                <Select 
                  value={formData.access_role} 
                  onValueChange={(value) => handleSelectChange('access_role', value)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select access role" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="owner">Owner only</SelectItem>
                    <SelectItem value="admin">Admins</SelectItem>
                    <SelectItem value="member">All members</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-sm text-muted-foreground">Who can access and use this AI agent</p>
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end space-x-4">
            <Button type="button" variant="outline" asChild disabled={isSubmitting}>
              <Link href="/ai">Cancel</Link>
            </Button>
            <Button type="submit" disabled={isSubmitting || !formData.name}>
              {isSubmitting ? (
                <>
                  <Loader2Icon className="mr-2 h-4 w-4 animate-spin" />
                  Creating...
                </>
              ) : (
                'Create AI Agent'
              )}
            </Button>
          </div>
        </form>
      </div>
    </AppShell>
  )
} 