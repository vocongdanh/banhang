"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { SearchIcon, FileIcon, ExternalLinkIcon, LoaderIcon } from "lucide-react"
import { useToast } from "@/hooks/use-toast"

interface VectorSearchProps {
  dataType?: string
  businessId?: number
  limit?: number
}

interface SearchResult {
  score: number
  content: string
  metadata: {
    file_id: number
    filename: string
    data_type: string
  }
  file?: {
    id: number
    name: string
    url: string
    uploaded_at: string
  }
}

export function VectorSearch({ dataType, businessId, limit = 5 }: VectorSearchProps) {
  const [query, setQuery] = useState("")
  const [results, setResults] = useState<SearchResult[]>([])
  const [isSearching, setIsSearching] = useState(false)
  const [hasSearched, setHasSearched] = useState(false)
  const { toast } = useToast()

  const handleSearch = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!query.trim() || query.trim().length < 2) {
      toast({
        title: "Lỗi",
        description: "Vui lòng nhập ít nhất 2 ký tự để tìm kiếm",
        variant: "destructive"
      })
      return
    }
    
    setIsSearching(true)
    setHasSearched(false)
    
    try {
      const response = await fetch("/api/search", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          query,
          businessId,
          dataType,
          limit,
        }),
      })
      
      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || "Không thể thực hiện tìm kiếm")
      }
      
      const data = await response.json()
      setResults(data.results || [])
      setHasSearched(true)
      
      if (data.results?.length === 0) {
        toast({
          title: "Thông báo",
          description: "Không tìm thấy kết quả nào phù hợp với từ khóa",
        })
      }
    } catch (error) {
      console.error("Search error:", error)
      toast({
        title: "Lỗi",
        description: error instanceof Error ? error.message : "Lỗi không xác định khi tìm kiếm",
        variant: "destructive"
      })
    } finally {
      setIsSearching(false)
    }
  }
  
  // Truncate text to a specific length
  const truncateText = (text: string, maxLength: number = 200) => {
    if (text.length <= maxLength) return text
    return text.substring(0, maxLength) + "..."
  }
  
  // Format and highlight matches in the content
  const highlightMatches = (content: string, query: string) => {
    if (!query || !content) return content
    
    const parts = content.split(new RegExp(`(${query})`, 'gi'))
    return parts.map((part, i) => 
      part.toLowerCase() === query.toLowerCase() 
        ? <mark key={i} className="bg-yellow-200 rounded-sm px-1">{part}</mark> 
        : part
    )
  }

  return (
    <div className="space-y-4">
      <form onSubmit={handleSearch} className="flex gap-2">
        <Input
          type="text"
          placeholder="Nhập từ khóa tìm kiếm..."
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          className="flex-1"
        />
        <Button type="submit" disabled={isSearching || !query.trim()}>
          {isSearching ? (
            <LoaderIcon className="h-4 w-4 animate-spin mr-2" />
          ) : (
            <SearchIcon className="h-4 w-4 mr-2" />
          )}
          Tìm kiếm
        </Button>
      </form>
      
      {hasSearched && results.length > 0 && (
        <div className="space-y-3 mt-6">
          <h3 className="text-lg font-medium">Kết quả tìm kiếm ({results.length})</h3>
          
          {results.map((result, index) => (
            <Card key={index} className="overflow-hidden">
              <CardContent className="p-4">
                <div className="flex items-start gap-3">
                  <div className="flex-shrink-0 mt-1">
                    <FileIcon className="h-5 w-5 text-blue-500" />
                  </div>
                  <div className="space-y-2 flex-1">
                    <div className="flex items-center justify-between">
                      <div className="font-medium">
                        {result.file?.name || result.metadata.filename}
                      </div>
                      <div className="text-xs text-muted-foreground">
                        Độ tương thích: {Math.round(result.score * 100)}%
                      </div>
                    </div>
                    
                    <div className="text-sm bg-slate-50 p-3 rounded border">
                      {highlightMatches(truncateText(result.content), query)}
                    </div>
                    
                    {result.file && (
                      <div className="flex justify-between items-center">
                        <div className="text-xs text-muted-foreground">
                          {result.file.uploaded_at}
                        </div>
                        <Button 
                          variant="ghost" 
                          size="sm"
                          className="h-8"
                          onClick={() => window.open(result.file?.url, "_blank")}
                        >
                          <ExternalLinkIcon className="h-4 w-4 mr-1" />
                          Xem tệp tin
                        </Button>
                      </div>
                    )}
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
      
      {hasSearched && results.length === 0 && (
        <div className="flex justify-center items-center h-40 border border-dashed rounded-lg mt-4">
          <p className="text-sm text-gray-500">Không tìm thấy kết quả nào phù hợp với từ khóa</p>
        </div>
      )}
    </div>
  )
} 