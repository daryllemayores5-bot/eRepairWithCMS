export type User = { id: number; name: string; email: string; role: 'customer' | 'technician' | 'admin' }
export type Option = { id: number; name: string; slug?: string; specialization_id?: number; device_type_id?: number }
export type Lookups = { device_types: Option[]; device_brands: Option[]; specializations: Option[]; repair_categories: Option[] }
export type Technician = {
  id: number; name: string; biography: string | null; city: string; province: string; years_experience: number
  latitude: number | null; longitude: number | null; photo_url: string | null
  availability: 'available' | 'busy' | 'unavailable'; verified: boolean; verification_status: string
  average_rating: number; reviews_count: number; specializations: Option[]; device_types: Option[]
  service_areas: { city: string; province: string }[]; matching: { score: number; reasons: Record<string, number> }
}
export type Page<T> = { data: T[]; meta?: { current_page: number; last_page: number; total: number }; current_page?: number; last_page?: number; total?: number }
export type Appointment = { id: number; repair_request_id: number; starts_at: string; ends_at: string; service_location: string; status: string; repair?: { id: number; reference: string; model: string; status: string } }
export type Review = { id: number; rating: number; body: string; created_at: string; customer?: { name: string } }
export type Repair = {
  id: number; reference: string; status: string; model: string; serial_number: string | null; problem_description: string
  device_type: string; device_brand: string; category: string; created_at: string; completed_at: string | null
  customer: { id: number; name: string }; technician: { id: number; name: string }
  history?: { id: number; new_status: string; previous_status: string | null; remarks: string | null; created_at: string; actor?: { name: string } }[]
  estimates?: { id: number; amount: string; currency: string; description: string; status: string }[]
  appointments?: Appointment[]; attachments?: { id: number; original_name: string }[]; review?: Review | null
}
export type Dashboard = { total: number; counts: Record<string, number>; activity?: { date: string; count: number }[]; recent: Repair[]; appointments: Appointment[]; average_rating: number | null }
export type Notice = { id: string; data: { repair_request_id: number; message: string }; read_at: string | null; created_at: string }
export type WebsiteContent = { hero_title: string | null; hero_subtitle: string | null; faqs: { question: string; answer: string }[]; announcements: { title: string; content: string }[]; pages: { slug: string; title: string; content: string }[] }
